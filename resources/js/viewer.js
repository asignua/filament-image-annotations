/*
 * Alpine component of the read-only viewer: the image with the annotation layer drawn over it and a toggle.
 * The layers and palette travel in data attributes (`data-layers`, `data-palette`), so the markup stays
 * valid for any content; the original image is never touched.
 *
 * Without an `x-ref="image"` it is a bare overlay over a picture drawn by someone else: `data-aspect` (height / width)
 * sizes the marks, `data-fit="cover"` crops them like an `object-fit: cover` picture, `data-interactive="false"` keeps
 * the pins from catching clicks.
 */
import { emit, hiddenByDefault, layerToggles, renderLayers } from './render.js'

export default function imageAnnotationsViewer() {
    return {
        ...layerToggles(),

        layers: [],

        raw: '',

        observer: null,

        palette: {},

        visible: true,

        aspect: 0.75,

        get count() {
            return this.layers.reduce((sum, layer) => sum + (layer.items?.length ?? 0), 0)
        },

        init() {
            this.read()
            this.hidden = hiddenByDefault(this.layers)

            const image = this.$refs.image

            if (image?.complete && image.naturalWidth > 0) {
                this.loaded()
            } else if (!image) {
                // An overlay over a picture drawn by someone else (a chat thumbnail): the aspect comes from the server.
                const aspect = Number.parseFloat(this.$el.dataset.aspect ?? '')

                if (aspect > 0) {
                    this.aspect = aspect
                }

                this.draw()
            }

            // A Livewire morph (a modal opened on the same page, a re-render after saving) resets the <svg> to the
            // empty one the server sent and may bring new layers in `data-layers`: follow both.
            if (typeof MutationObserver !== 'undefined') {
                this.observer = new MutationObserver(() => this.resync())
                this.observer.observe(this.$el, { attributes: true, attributeFilter: ['data-layers'] })

                if (this.$refs.svg) {
                    this.observer.observe(this.$refs.svg, { childList: true })
                }
            }
        },

        destroy() {
            this.observer?.disconnect()
        },

        read() {
            this.raw = this.$el.dataset.layers ?? ''

            try {
                const layers = JSON.parse(this.raw || 'null')
                this.layers = Array.isArray(layers) ? layers : []
                this.palette = JSON.parse(this.$el.dataset.palette ?? '{}') ?? {}
            } catch (error) {
                this.layers = []
            }
        },

        resync() {
            if ((this.$el.dataset.layers ?? '') !== this.raw) {
                this.read()
                this.draw()
            } else if (this.count > 0 && this.$refs.svg?.childElementCount === 0) {
                this.draw()
            }
        },

        loaded() {
            const image = this.$refs.image

            if (image && image.naturalWidth > 0) {
                this.aspect = image.naturalHeight / image.naturalWidth
            }

            this.draw()
        },

        draw() {
            const svg = this.$refs.svg

            if (!svg) {
                return
            }

            renderLayers(svg, this.layers, { palette: this.palette, aspect: this.aspect, interactive: this.$el.dataset.interactive !== 'false', hidden: this.hidden })

            // `data-fit="cover"`: the picture under the overlay is cropped like CSS `object-fit: cover`, crop the marks alike.
            if (this.$el.dataset.fit === 'cover') {
                svg.setAttribute('preserveAspectRatio', 'xMidYMid slice')
            }
        },

        drawLayers() {
            this.draw()
        },

        // A click on a numbered pin: the pro package (or the host) listens for `image-annotations:pin-click`.
        pinClick(event) {
            const pin = event.target instanceof Element ? event.target.closest('[data-pin]') : null

            if (!pin) {
                return
            }

            emit(this.$el, 'pin-click', {
                layer: pin.closest('[data-layer]')?.getAttribute('data-layer') ?? null,
                id: pin.getAttribute('data-id'),
                n: Number(pin.getAttribute('data-pin')),
            })
        },

        toggle() {
            this.visible = !this.visible
        },
    }
}
