/*
 * Alpine component of the annotation editor. Fetched on demand by Alpine's x-load, only on a page that opens it.
 *
 * `state` is `$wire.$entangle(statePath)` holding `{v: 1, items: [...]}`; points are normalised 0..1 of the
 * image. The shapes live in plain closure variables (not in Alpine's reactive proxy: a pen stroke is thousands
 * of points) and are written back to `state` only when a change is COMMITTED (pointer up, colour change,
 * undo...), never on pointermove. Drawing is done by render.js into a live <svg> above the <img>.
 *
 * Pointer events cover mouse, touch and pen. The original image is never modified.
 */
import { emit, layerToggles, renderLayers, renderShapes, resolveColor, strokeWidth, VIEW_WIDTH } from './render.js'

const SHORTCUTS = { v: 'select', a: 'arrow', r: 'rect', e: 'ellipse', l: 'line', p: 'pen', h: 'highlight', t: 'text', n: 'pin' }

const MAX_PIN = 999

const HISTORY_LIMIT = 100

const clamp01 = (value) => Math.min(1, Math.max(0, value))

const clone = (value) => JSON.parse(JSON.stringify(value))

const newId = () => Math.random().toString(36).slice(2, 12).padEnd(10, '0')

export default function imageAnnotator({ state, palette = {}, widths = [2, 4, 8], limits = {}, labels = {}, tools = [], underlays = [], layer = 'default' }) {
    const maxShapes = limits.shapes ?? 200
    const maxPoints = limits.points ?? 2000
    const maxText = limits.text ?? 500
    const colorNames = Object.keys(palette)

    // Not reactive on purpose, see the header comment.
    let items = []
    let history = []
    let cursor = -1
    let draft = null
    let drag = null
    let aspect = 0.75
    let observer = null
    let clearTimer = null

    return {
        state,

        labels,

        palette,

        widths,

        // The first enabled tool, `arrow` when it is among them.
        tool: tools.includes('arrow') || tools.length === 0 ? 'arrow' : tools[0],

        tools,

        // Read-only layers drawn below the editable one: never selectable, never part of `state`.
        underlays,

        ...layerToggles(),

        color: colorNames[0] ?? '#ef4444',

        width: widths[1] ?? widths[0] ?? 4,

        selectedId: null,

        canUndo: false,

        canRedo: false,

        count: 0,

        confirmClear: false,

        // `{x, y, id, text}` (x, y in 0..1) while the inline text box is open.
        editor: null,

        displayWidth: 0,

        get atLimit() {
            return this.count >= maxShapes
        },

        get hint() {
            return this.atLimit ? (labels.limit ?? '') : ''
        },

        get surfaceStyle() {
            return { cursor: this.tool === 'select' ? 'default' : this.tool === 'text' ? 'text' : 'crosshair' }
        },

        get editorStyle() {
            const editor = this.editor

            if (!editor) {
                return {}
            }

            const color = resolveColor(editor.color, palette)
            const size = (14 + this.width * 3) * (this.displayWidth / VIEW_WIDTH)

            return {
                left: `${editor.x * 100}%`,
                top: `${editor.y * 100}%`,
                color,
                fontSize: `${Math.max(11, size)}px`,
                background: color.toLowerCase() === '#ffffff' ? 'rgba(17,24,39,.65)' : 'rgba(255,255,255,.85)',
            }
        },

        swatchStyle(name) {
            return { background: palette[name] }
        },

        // ---- lifecycle ----------------------------------------------------------------------------

        init() {
            this.load(this.state)

            history = [JSON.stringify(items)]
            cursor = 0
            this.updateFlags()

            // The server (or a re-hydration) replaced the value under us.
            this.$watch('state', (value) => {
                if (JSON.stringify(value?.items ?? []) !== JSON.stringify(items)) {
                    this.load(value)
                    this.commitSilently()
                    this.redraw()
                }
            })

            this.$nextTick(() => {
                this.fit()

                if (this.$refs.image?.complete && this.$refs.image.naturalWidth > 0) {
                    this.imageLoaded()
                }

                if (typeof ResizeObserver !== 'undefined') {
                    observer = new ResizeObserver(() => this.fit())
                    observer.observe(this.$el)
                }
            })
        },

        destroy() {
            observer?.disconnect()
            clearTimeout(clearTimer)
        },

        load(value) {
            items = Array.isArray(value?.items) ? clone(value.items) : []
            this.selectedId = null
            this.count = items.length
        },

        // `max-height` on the image shrinks it AFTER the shrink-to-fit wrapper took the wider width, so the
        // wrapper (the coordinate space) would be wider than the picture. Pin it to the width the image
        // really renders at (same trick as the focal-point picker of filament-image-meta).
        fit() {
            const surface = this.$refs.surface
            const image = this.$refs.image

            if (!surface || !image) {
                return
            }

            surface.style.width = ''

            // offsetWidth, not getBoundingClientRect(): the modal is still scaling in (a transform) when this first runs.
            const width = image.offsetWidth

            if (width > 0) {
                surface.style.width = `${width}px`
                this.displayWidth = width
            }
        },

        imageLoaded() {
            const image = this.$refs.image

            if (image.naturalWidth > 0) {
                aspect = image.naturalHeight / image.naturalWidth
            }

            this.fit()
            this.redraw()
            this.drawLayers()
        },

        // ---- drawing ------------------------------------------------------------------------------

        redraw() {
            const svg = this.$refs.svg

            if (!svg) {
                return
            }

            const hidden = this.editor?.id
            const visible = items.filter((item) => item.id !== hidden)

            renderShapes(svg, draft ? [...visible, draft] : visible, { palette, aspect })

            const selected = this.selectedId && !draft ? this.node(this.selectedId) : null

            if (selected) {
                const box = selected.getBBox()
                const pad = 8
                const frame = document.createElementNS('http://www.w3.org/2000/svg', 'rect')

                frame.setAttribute('x', String(box.x - pad))
                frame.setAttribute('y', String(box.y - pad))
                frame.setAttribute('width', String(box.width + pad * 2))
                frame.setAttribute('height', String(box.height + pad * 2))
                frame.setAttribute('fill', 'none')
                frame.setAttribute('stroke', '#3b82f6')
                frame.setAttribute('stroke-width', '2')
                frame.setAttribute('stroke-dasharray', '8 6')
                frame.setAttribute('vector-effect', 'non-scaling-stroke')
                frame.setAttribute('pointer-events', 'none')
                frame.setAttribute('data-selection', 'true')
                svg.appendChild(frame)
            }
        },

        drawLayers() {
            const svg = this.$refs.under

            if (svg) {
                renderLayers(svg, underlays, { palette, aspect, hidden: this.hidden })
            }
        },

        node(id) {
            return this.$refs.svg?.querySelector(`[data-id="${CSS.escape(id)}"]`) ?? null
        },

        point(event) {
            const rect = this.$refs.image.getBoundingClientRect()

            return [clamp01((event.clientX - rect.left) / rect.width), clamp01((event.clientY - rect.top) / rect.height)]
        },

        // Keeps the angle (arrow/line) or the aspect (rect/ellipse) while Shift is held. Works in the
        // 1000-unit space so a diagonal is 45 degrees on screen, not in normalised coordinates.
        constrain(a, b, shift, type) {
            if (!shift) {
                return b
            }

            let dx = (b[0] - a[0]) * VIEW_WIDTH
            let dy = (b[1] - a[1]) * VIEW_WIDTH * aspect

            if (type === 'arrow' || type === 'line') {
                const step = Math.PI / 4
                const angle = Math.round(Math.atan2(dy, dx) / step) * step
                const length = Math.hypot(dx, dy)

                dx = Math.cos(angle) * length
                dy = Math.sin(angle) * length
            } else {
                const side = Math.max(Math.abs(dx), Math.abs(dy))

                dx = Math.sign(dx || 1) * side
                dy = Math.sign(dy || 1) * side
            }

            return [clamp01(a[0] + dx / VIEW_WIDTH), clamp01(a[1] + dy / (VIEW_WIDTH * aspect))]
        },

        hit(p) {
            const x = p[0] * VIEW_WIDTH
            const y = p[1] * VIEW_WIDTH * aspect

            for (let i = items.length - 1; i >= 0; i--) {
                const item = items[i]
                const node = this.node(item.id)

                if (!node) {
                    continue
                }

                const box = node.getBBox()
                const tolerance = 10 + strokeWidth(item) * (item.type === 'highlight' ? 2 : 0.5)

                if (x >= box.x - tolerance && x <= box.x + box.width + tolerance && y >= box.y - tolerance && y <= box.y + box.height + tolerance) {
                    return item
                }
            }

            return null
        },

        // ---- pointer ------------------------------------------------------------------------------

        start(event) {
            if (event.target === this.$refs.editor || (event.button !== undefined && event.button !== 0)) {
                return
            }

            this.commitText()
            this.resetConfirm()
            this.$refs.root.focus({ preventScroll: true })
            event.preventDefault()

            const p = this.point(event)

            if (this.tool === 'select') {
                const item = this.hit(p)

                this.selectedId = item?.id ?? null

                if (item?.type === 'pin') {
                    emit(this.$el, 'pin-click', { layer, id: item.id, n: item.n })
                }

                if (item) {
                    this.color = this.palette[item.color] !== undefined ? item.color : this.color
                    this.width = item.width ?? this.width
                    drag = { kind: 'move', start: p, original: clone(item.pts), id: item.id, moved: false }
                    this.$refs.surface.setPointerCapture?.(event.pointerId)
                }

                this.redraw()

                return
            }

            this.selectedId = null

            if (this.atLimit) {
                this.redraw()

                return
            }

            if (this.tool === 'pin') {
                this.placePin(p)

                return
            }

            if (this.tool === 'text') {
                this.editor = { x: p[0], y: p[1], id: null, text: '', color: this.color }
                this.$nextTick(() => this.$refs.editor?.focus())

                return
            }

            const single = this.tool === 'pen' || this.tool === 'highlight'

            draft = { id: newId(), type: this.tool, pts: single ? [p] : [p, p], color: this.color, width: this.width }
            drag = { kind: 'draw' }
            this.$refs.surface.setPointerCapture?.(event.pointerId)
            this.redraw()
        },

        // A click drops the next number (highest in this layer + 1) and is committed at once.
        placePin(p) {
            const n = items.reduce((max, item) => (item.type === 'pin' ? Math.max(max, Number(item.n) || 0) : max), 0) + 1

            if (n > MAX_PIN) {
                return
            }

            const pin = { id: newId(), type: 'pin', pts: [p], color: this.color, width: this.width, n }

            items.push(pin)
            this.commit()
            emit(this.$el, 'pin-added', { id: pin.id, n })
        },

        move(event) {
            if (!drag) {
                return
            }

            const p = this.point(event)

            if (drag.kind === 'move') {
                const xs = drag.original.map((pt) => pt[0])
                const ys = drag.original.map((pt) => pt[1])
                const dx = Math.min(1 - Math.max(...xs), Math.max(-Math.min(...xs), p[0] - drag.start[0]))
                const dy = Math.min(1 - Math.max(...ys), Math.max(-Math.min(...ys), p[1] - drag.start[1]))
                const item = items.find((candidate) => candidate.id === drag.id)

                if (item) {
                    item.pts = drag.original.map(([x, y]) => [x + dx, y + dy])
                    drag.moved = drag.moved || dx !== 0 || dy !== 0
                    this.redraw()
                }

                return
            }

            if (!draft) {
                return
            }

            if (draft.type === 'pen' || draft.type === 'highlight') {
                const last = draft.pts[draft.pts.length - 1]
                const rect = this.$refs.image.getBoundingClientRect()

                // Closer than ~2 screen pixels adds nothing visible, only weight.
                if (Math.hypot((p[0] - last[0]) * rect.width, (p[1] - last[1]) * rect.height) < 2 || draft.pts.length >= maxPoints) {
                    return
                }

                draft.pts.push(p)
            } else {
                draft.pts[1] = this.constrain(draft.pts[0], p, event.shiftKey, draft.type)
            }

            this.redraw()
        },

        end(event) {
            this.$refs.surface.releasePointerCapture?.(event.pointerId)

            const current = drag

            drag = null

            if (current?.kind === 'move') {
                if (current.moved) {
                    this.commit()
                }

                return
            }

            if (!draft) {
                return
            }

            const shape = draft

            draft = null

            const extent = shape.pts.length < 2 ? 0 : Math.hypot((shape.pts[shape.pts.length - 1][0] - shape.pts[0][0]) * VIEW_WIDTH, (shape.pts[shape.pts.length - 1][1] - shape.pts[0][1]) * VIEW_WIDTH * aspect)
            const valid = shape.type === 'pen' || shape.type === 'highlight' ? shape.pts.length >= 2 : extent > 4

            if (valid) {
                items.push(shape)
                this.selectedId = null
                this.commit()
            } else {
                this.redraw()
            }
        },

        // Double click on a text item with the select tool edits it.
        edit(event) {
            if (this.tool !== 'select') {
                return
            }

            const item = this.hit(this.point(event))

            if (item?.type === 'text') {
                this.editor = { x: item.pts[0][0], y: item.pts[0][1], id: item.id, text: item.text ?? '', color: item.color }
                this.selectedId = null
                this.redraw()
                this.$nextTick(() => {
                    this.$refs.editor?.focus()
                    this.$refs.editor?.select()
                })
            }
        },

        // ---- text box -----------------------------------------------------------------------------

        commitText() {
            const editor = this.editor

            if (!editor) {
                return
            }

            // Cleared first: the blur fired by hiding the box must not commit a second time.
            this.editor = null

            const text = String(editor.text ?? '')
                .trim()
                .slice(0, maxText)

            if (editor.id) {
                const index = items.findIndex((item) => item.id === editor.id)

                if (index >= 0) {
                    if (text === '') {
                        items.splice(index, 1)
                    } else {
                        items[index].text = text
                    }

                    this.commit()
                }

                return
            }

            if (text !== '' && !this.atLimit) {
                items.push({ id: newId(), type: 'text', pts: [[editor.x, editor.y]], color: editor.color, width: this.width, text })
                this.commit()
            }
        },

        cancelText() {
            this.editor = null
            this.redraw()
        },

        // The box is removed from the DOM, which would drop the focus to <body> and kill the keyboard shortcuts.
        refocus() {
            this.$nextTick(() => this.$refs.root?.focus({ preventScroll: true }))
        },

        textKey(event) {
            event.stopPropagation()

            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault()
                this.commitText()
                this.refocus()
            } else if (event.key === 'Escape') {
                // Inside a modal Escape closes the dialog; here it only cancels the box.
                event.preventDefault()
                this.cancelText()
                this.refocus()
            }
        },

        // ---- toolbar / keyboard -------------------------------------------------------------------

        setTool(tool) {
            this.commitText()
            this.resetConfirm()
            this.tool = tool

            if (tool !== 'select') {
                this.selectedId = null
                this.redraw()
            }
        },

        setColor(name) {
            this.color = name

            const item = this.selectedItem()

            if (item) {
                item.color = name
                this.commit()
            }
        },

        setWidth(width) {
            this.width = width

            const item = this.selectedItem()

            if (item) {
                item.width = width
                this.commit()
            }
        },

        selectedItem() {
            return this.selectedId ? items.find((item) => item.id === this.selectedId) : undefined
        },

        deleteSelected() {
            if (!this.selectedId) {
                return
            }

            items = items.filter((item) => item.id !== this.selectedId)
            this.selectedId = null
            this.commit()
        },

        clear() {
            if (items.length === 0) {
                return
            }

            if (!this.confirmClear) {
                this.confirmClear = true
                clearTimeout(clearTimer)
                clearTimer = setTimeout(() => (this.confirmClear = false), 3000)

                return
            }

            this.resetConfirm()
            items = []
            this.selectedId = null
            this.commit()
        },

        resetConfirm() {
            this.confirmClear = false
            clearTimeout(clearTimer)
        },

        undo() {
            if (cursor > 0) {
                cursor--
                this.restore()
            }
        },

        redo() {
            if (cursor < history.length - 1) {
                cursor++
                this.restore()
            }
        },

        restore() {
            items = JSON.parse(history[cursor])

            if (this.selectedId && !items.some((item) => item.id === this.selectedId)) {
                this.selectedId = null
            }

            this.flush()
        },

        // When the modal opens the focus sits on its close button / the page, not in the editor, so the shortcuts
        // did nothing until the picture was clicked. Keys pressed anywhere in the SAME modal (or on <body>) count
        // too; keys inside the editor are already handled by the root's own listener.
        windowKeys(event) {
            const root = this.$refs.root
            const target = event.target

            if (!root || root.offsetParent === null || (target instanceof Node && root.contains(target))) {
                return
            }

            // Filament focuses the window's CONTAINER (`.fi-modal-window-ctn`, a parent of `.fi-modal-window`) on open.
            const scope = root.closest('.fi-modal-window-ctn') ?? root.closest('.fi-modal-window') ?? root

            if (target !== document.body && !(target instanceof Node && scope.contains(target))) {
                return
            }

            this.keys(event)
        },

        keys(event) {
            const target = event.target

            if (target && (target.tagName === 'TEXTAREA' || target.tagName === 'INPUT')) {
                return
            }

            const key = event.key.toLowerCase()
            const modifier = event.ctrlKey || event.metaKey

            if (modifier && key === 'z') {
                event.preventDefault()
                event.shiftKey ? this.redo() : this.undo()

                return
            }

            if (modifier && key === 'y') {
                event.preventDefault()
                this.redo()

                return
            }

            if (modifier || event.altKey) {
                return
            }

            if ((key === 'delete' || key === 'backspace') && this.selectedId) {
                event.preventDefault()
                this.deleteSelected()

                return
            }

            if (key === 'escape' && this.selectedId) {
                event.stopPropagation()
                this.selectedId = null
                this.redraw()

                return
            }

            if (SHORTCUTS[key] && tools.includes(SHORTCUTS[key]) && !event.shiftKey) {
                event.preventDefault()
                this.setTool(SHORTCUTS[key])
            }
        },

        // ---- history / state ----------------------------------------------------------------------

        // A committed change: one history entry and one write to the Livewire state.
        commit() {
            history = history.slice(0, cursor + 1)
            history.push(JSON.stringify(items))

            if (history.length > HISTORY_LIMIT) {
                history.shift()
            }

            cursor = history.length - 1
            this.flush()
        },

        // Re-bases the history on a value that came from outside.
        commitSilently() {
            history = [JSON.stringify(items)]
            cursor = 0
            this.updateFlags()
        },

        flush() {
            this.state = { v: 1, items: clone(items) }
            this.updateFlags()
            this.redraw()
            emit(this.$el, 'change', { items: clone(items) })
        },

        updateFlags() {
            this.count = items.length
            this.canUndo = cursor > 0
            this.canRedo = cursor < history.length - 1
        },
    }
}
