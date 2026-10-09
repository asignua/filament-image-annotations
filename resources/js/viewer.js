/*
 * Alpine component of the read-only viewer: the image with the annotation layer drawn over it and a toggle.
 * The layer and palette travel in data attributes (`data-annotations`, `data-palette`), so the markup stays
 * valid for any content; the original image is never touched.
 */
import { renderShapes } from './render.js'

export default function imageAnnotationsViewer() {
    return {
        items: [],

        raw: '',

        observer: null,

        palette: {},

        visible: true,

        aspect: 0.75,

        get count() {
            return this.items.length
        },

        init() {
            this.read()

            const image = this.$refs.image

            if (image?.complete && image.naturalWidth > 0) {
                this.loaded()
            }

            // A Livewire morph (a modal opened on the same page, a re-render after saving) resets the <svg> to the
            // empty one the server sent and may bring a new layer in `data-annotations`: follow both.
            if (typeof MutationObserver !== 'undefined') {
                this.observer = new MutationObserver(() => this.resync())
                this.observer.observe(this.$el, { attributes: true, attributeFilter: ['data-annotations'] })

                if (this.$refs.svg) {
                    this.observer.observe(this.$refs.svg, { childList: true })
                }
            }
        },

        destroy() {
            this.observer?.disconnect()
        },

        read() {
            this.raw = this.$el.dataset.annotations ?? ''

            try {
                const layer = JSON.parse(this.raw || 'null')
                this.items = Array.isArray(layer?.items) ? layer.items : []
                this.palette = JSON.parse(this.$el.dataset.palette ?? '{}') ?? {}
            } catch (error) {
                this.items = []
            }
        },

        resync() {
            if ((this.$el.dataset.annotations ?? '') !== this.raw) {
                this.read()
                this.draw()
            } else if (this.items.length > 0 && this.$refs.svg?.childElementCount === 0) {
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
                renderShapes(this.$refs.svg, this.items, { palette: this.palette, aspect: this.aspect })
            }
        },

        toggle() {
            this.visible = !this.visible
        },
    }
}
