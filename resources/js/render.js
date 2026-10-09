/*
 * Shared, Alpine-free renderer of the annotation layer. Used by the editor (live preview) and the viewer.
 *
 * Coordinate space: the <svg> gets `viewBox="0 0 1000 H"` with H = 1000 * naturalHeight / naturalWidth,
 * so a stored point (x, y in 0..1) lands at (x * 1000, y * H). A stored stroke width is therefore
 * "pixels of a 1000px-wide picture" times STROKE_FACTOR, which looks the same at any display size and for
 * any resolution of the original. Text size derives from the width the same way.
 *
 * Everything is built with createElementNS / setAttribute / textContent. Never innerHTML: the text of an
 * annotation is user input.
 */
export const SVG_NS = 'http://www.w3.org/2000/svg'

export const VIEW_WIDTH = 1000

const STROKE_FACTOR = 1.5

export function strokeWidth(item, scale = 1) {
    return Math.max(1, Number(item.width) || 4) * STROKE_FACTOR * scale
}

export function fontSize(item, scale = 1) {
    return (14 + (Number(item.width) || 4) * 3) * scale
}

export function resolveColor(color, palette = {}) {
    if (typeof color === 'string' && Object.prototype.hasOwnProperty.call(palette, color)) {
        return palette[color]
    }

    return typeof color === 'string' && /^#[0-9a-f]{6}$/i.test(color) ? color : Object.values(palette)[0] ?? '#ef4444'
}

function el(name, attributes = {}) {
    const node = document.createElementNS(SVG_NS, name)

    for (const [key, value] of Object.entries(attributes)) {
        node.setAttribute(key, String(value))
    }

    return node
}

const round = (n) => Math.round(n * 100) / 100

function smoothPath(points) {
    if (points.length === 1) {
        const [x, y] = points[0]

        return `M${round(x)} ${round(y)}l0.01 0`
    }

    if (points.length === 2) {
        return `M${round(points[0][0])} ${round(points[0][1])}L${round(points[1][0])} ${round(points[1][1])}`
    }

    // Quadratic curves through the midpoints, with the real points as control points.
    let d = `M${round(points[0][0])} ${round(points[0][1])}`

    for (let i = 1; i < points.length - 1; i++) {
        const mx = (points[i][0] + points[i + 1][0]) / 2
        const my = (points[i][1] + points[i + 1][1]) / 2

        d += `Q${round(points[i][0])} ${round(points[i][1])} ${round(mx)} ${round(my)}`
    }

    const last = points[points.length - 1]

    return `${d}L${round(last[0])} ${round(last[1])}`
}

function buildItem(item, ctx) {
    const { height, palette, scale } = ctx
    const points = (item.pts ?? []).map(([x, y]) => [x * VIEW_WIDTH, y * height])
    const color = resolveColor(item.color, palette)
    const width = strokeWidth(item, scale)
    const group = el('g', { 'data-id': item.id, 'data-type': item.type })

    if (points.length === 0) {
        return group
    }

    const stroke = { stroke: color, 'stroke-width': round(width), fill: 'none', 'stroke-linecap': 'round', 'stroke-linejoin': 'round' }

    switch (item.type) {
        case 'arrow': {
            if (points.length < 2) {
                break
            }

            const [[x1, y1], [x2, y2]] = points
            const angle = Math.atan2(y2 - y1, x2 - x1)
            const head = Math.max(width * 3.2, 14 * scale)
            const spread = Math.PI / 7
            const length = Math.hypot(x2 - x1, y2 - y1)
            // The shaft stops inside the head so a thick line does not poke out of the tip.
            const inset = Math.min(head * 0.7, length)
            const bx = x2 - Math.cos(angle) * inset
            const by = y2 - Math.sin(angle) * inset

            group.appendChild(el('line', { ...stroke, x1: round(x1), y1: round(y1), x2: round(bx), y2: round(by) }))
            group.appendChild(
                el('polygon', {
                    fill: color,
                    stroke: color,
                    'stroke-width': round(width * 0.3),
                    'stroke-linejoin': 'round',
                    points: [
                        [x2, y2],
                        [x2 - head * Math.cos(angle - spread), y2 - head * Math.sin(angle - spread)],
                        [x2 - head * Math.cos(angle + spread), y2 - head * Math.sin(angle + spread)],
                    ]
                        .map(([x, y]) => `${round(x)},${round(y)}`)
                        .join(' '),
                }),
            )

            break
        }

        case 'line': {
            if (points.length < 2) {
                break
            }

            group.appendChild(el('line', { ...stroke, x1: round(points[0][0]), y1: round(points[0][1]), x2: round(points[1][0]), y2: round(points[1][1]) }))

            break
        }

        case 'rect': {
            if (points.length < 2) {
                break
            }

            const [[x1, y1], [x2, y2]] = points

            group.appendChild(el('rect', { ...stroke, x: round(Math.min(x1, x2)), y: round(Math.min(y1, y2)), width: round(Math.abs(x2 - x1)), height: round(Math.abs(y2 - y1)) }))

            break
        }

        case 'ellipse': {
            if (points.length < 2) {
                break
            }

            const [[x1, y1], [x2, y2]] = points

            group.appendChild(el('ellipse', { ...stroke, cx: round((x1 + x2) / 2), cy: round((y1 + y2) / 2), rx: round(Math.abs(x2 - x1) / 2), ry: round(Math.abs(y2 - y1) / 2) }))

            break
        }

        case 'pen': {
            group.appendChild(el('path', { ...stroke, d: smoothPath(points) }))

            break
        }

        case 'highlight': {
            group.setAttribute('style', 'mix-blend-mode:multiply')
            group.appendChild(el('path', { ...stroke, 'stroke-width': round(width * 4), 'stroke-opacity': 0.35, d: smoothPath(points) }))

            break
        }

        case 'text': {
            const size = fontSize(item, scale)
            const lines = String(item.text ?? '').split('\n')
            const halo = color.toLowerCase() === '#ffffff' ? '#111827' : '#ffffff'
            const text = el('text', {
                x: round(points[0][0]),
                y: round(points[0][1] + size),
                fill: color,
                stroke: halo,
                'stroke-width': round(size * 0.22),
                'stroke-linejoin': 'round',
                'paint-order': 'stroke',
                'font-size': round(size),
                'font-weight': 600,
                'font-family': 'system-ui, -apple-system, "Segoe UI", Roboto, sans-serif',
            })

            lines.forEach((line, index) => {
                const span = el('tspan', { x: round(points[0][0]), ...(index > 0 ? { dy: round(size * 1.2) } : {}) })
                // An empty tspan has no height; a non-breaking space keeps the blank line.
                span.textContent = line === '' ? ' ' : line
                text.appendChild(span)
            })

            group.appendChild(text)

            break
        }
    }

    return group
}

/**
 * Clears and redraws `svg`. `aspect` is naturalHeight / naturalWidth, `scale` an extra multiplier for strokes
 * and text (1 by default).
 */
export function renderShapes(svg, items, { palette = {}, aspect = 0.75, scale = 1 } = {}) {
    const height = VIEW_WIDTH * (aspect > 0 ? aspect : 0.75)

    svg.setAttribute('viewBox', `0 0 ${VIEW_WIDTH} ${round(height)}`)
    svg.setAttribute('preserveAspectRatio', 'none')
    svg.replaceChildren()

    for (const item of items ?? []) {
        svg.appendChild(buildItem(item, { height, palette, scale }))
    }

    return height
}
