import '../css/field.css'

document.addEventListener('alpine:init', () => {
    Alpine.data('flexibleLayouts', (url, column, blockTitles = {}, flPath = '') => ({
        url: url,
        column: column,
        blockTitles: blockTitles,
        flPath: flPath || column,
        root: null,
        blocksContainer: null,
        tabBar: null,
        activeTab: 0,

        init() {
            this.root = this.$el
            this.blocksContainer = this.root.querySelector(':scope > ._fl-blocks')
            this.tabBar = this.root.querySelector(':scope > ._fl-tabs')

            this.resolveReindex()

            const t = this

            this.updateTabStyles()

            this.tabBar.addEventListener('click', function(e) {
                const tab = e.target.closest('._fl-tab')
                if (!tab) return
                const index = Array.from(t.tabBar.querySelectorAll(':scope > ._fl-tab')).indexOf(tab)
                t.switchTab(index)
            })

            MoonShine.iterable.sortable(
                this.tabBar, null, 'fl-tabs-' + this.column, null,
                { handle: '._fl-tab-grip' },
                function() { t.syncBlockOrder(); t.resolveReindex() },
            )
        },

        resolveReindex() {
            const t = this

            this.$nextTick(function() {
                t._directBlocks().forEach(function(block, i) {
                    block.setAttribute('data-row-key', i)
                })

                MoonShine.iterable.reindex(
                    t.root,
                    ':scope > ._fl-blocks > ._fl-block',
                    '._fl-block',
                )
            })
        },

        add(name) {
            const t = this

            const counts = {}
            t._directBlocks().forEach(function(block) {
                const types = block.querySelectorAll('._fl-type')
                for (const input of types) {
                    if (input.closest('[data-top-level]') === t.root) {
                        counts[input.value] = (counts[input.value] || 0) + 1
                        break
                    }
                }
            })

            MoonShine.request(t, t.url, 'post', {
                field: t.column,
                path: t.flPath,
                name: name,
                counts: counts,
            }, {}, {
                afterResponse: function(data) {
                    const html = data.blockHtml ?? ''
                    const newIndex = t._directBlocks().length

                    const wrapper = document.createElement('div')
                    wrapper.className = '_fl-block'
                    wrapper.setAttribute('data-row-key', newIndex)
                    wrapper.innerHTML = html
                    t.blocksContainer.appendChild(wrapper)

                    const tabBtn = document.createElement('button')
                    tabBtn.type = 'button'
                    tabBtn.className = '_fl-tab'
                    tabBtn.setAttribute('data-orig-idx', newIndex)
                    tabBtn.innerHTML = '<span class="_fl-tab-grip">⠿</span><span class="_fl-tab-label">' + (data.blockTitle || t.blockTitles[name] || name) + '</span>'
                    t.tabBar.appendChild(tabBtn)

                    t.switchTab(newIndex)
                    t.resolveReindex()

                    t.$nextTick(function() {
                        document.dispatchEvent(
                            new CustomEvent('flexible-layouts:block-added', {
                                bubbles: true,
                                detail: { name: name, column: t.column },
                            }),
                        )
                    })
                },
            })
        },

        remove() {
            const block = this.$el.closest('._fl-block')
            if (!block) return

            const blocks = this._directBlocks()
            const tabIndex = blocks.indexOf(block)

            block.remove()

            const tabs = Array.from(this.tabBar.querySelectorAll(':scope > ._fl-tab'))
            if (tabs[tabIndex]) tabs[tabIndex].remove()

            if (this.activeTab > tabIndex) {
                this.activeTab--
            } else if (this.activeTab >= blocks.length - 1) {
                this.activeTab = Math.max(0, this.activeTab - 1)
            }

            this.showActiveBlock()
            this.updateTabStyles()
            this.resolveReindex()
        },

        _directBlocks() {
            if (!this.blocksContainer) return []
            return Array.from(this.blocksContainer.querySelectorAll(':scope > ._fl-block'))
        },

        switchTab(index) {
            this.activeTab = index
            this.showActiveBlock()
            this.updateTabStyles()
        },

        showActiveBlock() {
            const t = this
            this._directBlocks().forEach(function(block, i) {
                block.classList.toggle('hidden', i !== t.activeTab)
            })
        },

        updateTabStyles() {
            const t = this
            Array.from(this.tabBar.querySelectorAll(':scope > ._fl-tab')).forEach(function(tab, i) {
                tab.classList.toggle('_fl-tab--active', i === t.activeTab)
            })
        },

        syncBlockOrder() {
            const tabs = Array.from(this.tabBar.querySelectorAll(':scope > ._fl-tab'))
            const blocks = this._directBlocks()
            const t = this

            tabs.forEach(function(tab) {
                const origIdx = parseInt(tab.dataset.origIdx)
                if (!isNaN(origIdx) && blocks[origIdx]) {
                    t.blocksContainer.appendChild(blocks[origIdx])
                }
            })
        },
    }))
})
