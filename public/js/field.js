document.addEventListener('alpine:init', () => {
    Alpine.data('flexibleLayouts', (url, column, mode = 'accordion', blockTitles = {}) => ({
        url: url,
        column: column,
        mode: mode,
        blockTitles: blockTitles,
        root: null,
        blocksContainer: null,
        tabBar: null,
        activeTab: 0,

        init() {
            this.root = this.$root
            this.blocksContainer = this.root.querySelector('._flexible-blocks')
            this.tabBar = this.root.querySelector('.fl-tabs')

            this._reindex()

            const t = this

            if (this.mode === 'tabs') {
                this._showActiveBlock()

                MoonShine.iterable.sortable(
                    this.tabBar,
                    null,
                    'flexibleLayoutsTabs',
                    null,
                    { handle: '.fl-tab-grip' },
                    function(evt) {
                        t._syncBlockOrder(evt.oldIndex, evt.newIndex)
                        t._reindex()
                    }
                )
            } else {
                MoonShine.iterable.sortable(
                    this.blocksContainer,
                    null,
                    'flexibleLayouts',
                    null,
                    { handle: '.handle' },
                    function(evt) {
                        t._reindex()
                    }
                )
            }
        },

        switchTab(index) {
            this.activeTab = index
            this._showActiveBlock()
        },

        add(name) {
            const t = this

            let blocksCount = {}
            const types = this.root.querySelectorAll('._type-value')
            types.forEach(function(l) {
                blocksCount[l.value] = blocksCount[l.value] ? blocksCount[l.value] + 1 : 1
            })

            MoonShine.request(t, t.url, 'post', {
                field: t.column,
                name: name,
                counts: blocksCount
            }, {}, {
                afterResponse: function(data) {
                    const html = data.html ?? data.htmlData?.[0]?.html ?? ''

                    if (t.mode === 'tabs') {
                        const newIndex = t.blocksContainer.querySelectorAll('._flexible-block').length

                        const blockDiv = document.createElement('div')
                        blockDiv.className = '_flexible-block hidden'
                        blockDiv.innerHTML = html
                        t.blocksContainer.appendChild(blockDiv)

                        const tabBtn = document.createElement('button')
                        tabBtn.type = 'button'
                        tabBtn.className = 'fl-tab px-3 py-2 text-sm font-medium cursor-grab border-b-2 transition-colors whitespace-nowrap flex items-center gap-1 border-transparent text-gray-500 hover:text-gray-700'
                        tabBtn.innerHTML = '<span class="fl-tab-grip opacity-30 hover:opacity-60">⠿</span><span>' + (t.blockTitles[name] || name) + '</span>'
                        tabBtn.addEventListener('click', function() {
                            t.switchTab(Array.from(t.tabBar.children).indexOf(tabBtn))
                        })
                        t.tabBar.appendChild(tabBtn)

                        t.switchTab(newIndex)
                    } else {
                        const tempContainer = document.createElement('div')
                        tempContainer.innerHTML = html

                        while (tempContainer.firstChild) {
                            t.blocksContainer.appendChild(tempContainer.firstChild)
                        }
                    }

                    t._reindex()

                    t.$nextTick(function() {
                        document.dispatchEvent(
                            new CustomEvent('flexible-layouts:block-added', {
                                bubbles: true,
                                detail: { name: name, column: t.column },
                            }),
                        )
                    })
                }
            })
        },

        remove() {
            this.$el.closest('._flexible-block').remove()
            this._reindex()
        },

        removeActive() {
            const blocks = Array.from(this.blocksContainer.querySelectorAll('._flexible-block'))
            const tabs = Array.from(this.tabBar.children)

            if (this.activeTab >= blocks.length) {
                return
            }

            blocks[this.activeTab].remove()
            if (tabs[this.activeTab]) {
                tabs[this.activeTab].remove()
            }

            if (this.activeTab >= blocks.length - 1) {
                this.activeTab = Math.max(0, blocks.length - 2)
            }

            this._showActiveBlock()
            this._reindex()
        },

        _showActiveBlock() {
            const blocks = this.blocksContainer.querySelectorAll('._flexible-block')
            blocks.forEach((block, i) => {
                block.classList.toggle('hidden', i !== this.activeTab)
            })
        },

        _syncBlockOrder(oldIndex, newIndex) {
            const blocks = Array.from(this.blocksContainer.children)
            const moved = blocks.splice(oldIndex, 1)[0]
            blocks.splice(newIndex, 0, moved)

            blocks.forEach(b => this.blocksContainer.appendChild(b))

            if (this.activeTab === oldIndex) {
                this.activeTab = newIndex
            } else if (oldIndex < this.activeTab && newIndex >= this.activeTab) {
                this.activeTab--
            } else if (oldIndex > this.activeTab && newIndex <= this.activeTab) {
                this.activeTab++
            }

            this._showActiveBlock()
        },

        _reindex() {
            const t = this

            this.$nextTick(function() {
                MoonShine.iterable.reindex(
                    t.blocksContainer,
                    '._flexible-block'
                )
            })
        }
    }))
})
