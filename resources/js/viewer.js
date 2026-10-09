/*
 * Alpine component of the read-only viewer: the image with the annotation layer drawn over it and a toggle.
 * The layers and palette travel in data attributes (`data-layers`, `data-palette`), so the markup stays
 * valid for any content; the original image is never touched.
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
            if (this.$refs.svg) {
                renderLayers(this.$refs.svg, this.layers, { palette: this.palette, aspect: this.aspect, interactive: true, hidden: this.hidden })
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
