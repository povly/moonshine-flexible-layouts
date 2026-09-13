import '../css/field.css'

document.addEventListener('alpine:init', () => {
    Alpine.data('flexibleLayouts', (url, column, blockMeta = {}, flPath = '') => ({
        url: url,
        column: column,
        blockMeta: blockMeta,
        flPath: flPath || column,
        root: null,
        blocksContainer: null,
        tabBar: null,
        activeTab: 0,

        pickerOpen: false,
        pickerSearch: '',
        pickerCategory: null,
        _pickerReturnFocus: null,

        // Operation lock — prevents double-click / drag-during-pending-AJAX from
        // desyncing UI state. Reset in both afterResponse and errorCallback and
        // via a 30s safety timeout (in case MoonShine.request never calls either).
        _isMutating: false,

        init() {
            this.root = this.$el
            this.blocksContainer = this.root.querySelector(':scope > ._fl-blocks')
            this.tabBar = this.root.querySelector(':scope > ._fl-tabs')

            this.restoreTypeValues()

            const t = this

            this.assignUids()
            this.resolveReindex()

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
                function() {
                    t.syncBlockOrder()
                    t.resolveReindex()
                },
            )
        },

        destroy() {
            // Null references so Alpine can GC this component. SortableJS
            // cleanup relies on DOM element removal — MoonShine admin does
            // full page loads so this is sufficient. If MoonShine ever adopts
            // Turbolinks-style navigation, would need Sortable.get().destroy().
            this.root = null
            this.blocksContainer = null
            this.tabBar = null
        },

        restoreTypeValues() {
            if (!this.root) return
            const blocks = this.root.querySelectorAll(':scope > ._fl-blocks > ._fl-block')
            blocks.forEach(function(block) {
                const correctType = block.getAttribute('data-correct-type')
                if (!correctType) return
                const typeInput = block.querySelector(':scope > ._fl-type')
                if (!typeInput) return
                if (typeInput.value !== correctType) {
                    if (import.meta.env.DEV) {
                        console.warn('[FlexibleLayouts] _type mismatch — fixing', {
                            name: typeInput.getAttribute('name'),
                            wasValue: typeInput.value,
                            correctType: correctType,
                        })
                    }
                    typeInput.value = correctType
                    typeInput.setAttribute('value', correctType)
                }
            })
        },

        _genUid() {
            return 'fl-' + this.column + '-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 11)
        },

        assignUids() {
            const tabs = Array.from(this.tabBar.querySelectorAll(':scope > ._fl-tab'))
            const blocks = this._directBlocks()
            const t = this

            tabs.forEach(function(tab, i) {
                if (!tab.dataset.flUid) {
                    const uid = t._genUid()
                    tab.setAttribute('data-fl-uid', uid)
                    if (blocks[i] && !blocks[i].dataset.flUid) {
                        blocks[i].setAttribute('data-fl-uid', uid)
                    }
                } else if (blocks[i] && !blocks[i].dataset.flUid) {
                    blocks[i].setAttribute('data-fl-uid', tab.dataset.flUid)
                }
            })
        },

        get pickerCategories() {
            const cats = new Set()
            for (const name in this.blockMeta) {
                if (this.blockMeta[name].category) {
                    cats.add(this.blockMeta[name].category)
                }
            }
            return Array.from(cats).sort()
        },

        get pickerFiltered() {
            const search = this.pickerSearch.toLowerCase().trim()
            const result = {}
            for (const name in this.blockMeta) {
                const meta = this.blockMeta[name]
                if (this.pickerCategory !== null && meta.category !== this.pickerCategory) continue
                if (search) {
                    const haystack = (meta.title + ' ' + name + ' ' + (meta.description || '')).toLowerCase()
                    if (!haystack.includes(search)) continue
                }
                result[name] = meta
            }
            return result
        },

        openPicker() {
            this._pickerReturnFocus = document.activeElement
            this.pickerOpen = true
            this.pickerSearch = ''
            this.pickerCategory = null
            const t = this
            if (import.meta.env.DEV) {
                console.debug('[FlexibleLayouts] picker focus trap engaged', { column: t.column })
            }
            this.$nextTick(function() {
                t.$refs.searchInput && t.$refs.searchInput.focus()
            })
        },

        closePicker() {
            this.pickerOpen = false

            if (this._pickerReturnFocus && this._pickerReturnFocus.isConnected) {
                this._pickerReturnFocus.focus()
                if (import.meta.env.DEV) {
                    console.debug('[FlexibleLayouts] picker focus trap released', { column: this.column })
                }
            }
            this._pickerReturnFocus = null
        },

        // Keep Tab cycling inside the picker dialog (aria-modal pattern).
        trapTab(e) {
            const picker = e.target.closest('._fl-picker')
            if (!picker) return

            const focusables = Array.from(
                picker.querySelectorAll('button, input, select, textarea, [tabindex]:not([tabindex="-1"])')
            ).filter(function(el) {
                return el.offsetParent !== null
            })
            if (focusables.length === 0) return

            const first = focusables[0]
            const last = focusables[focusables.length - 1]
            const active = document.activeElement

            if (e.shiftKey && active === first) {
                e.preventDefault()
                last.focus()
            } else if (!e.shiftKey && active === last) {
                e.preventDefault()
                first.focus()
            }
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
            // Operation lock — rapid double-click or drag-during-pending would
            // otherwise fire two requests and desync the UI.
            if (this._isMutating) {
                if (import.meta.env.DEV) {
                    console.warn('[FlexibleLayouts] add() blocked by operation lock', { column: this.column, name })
                }
                return
            }
            this._isMutating = true

            // Safety reset in case neither afterResponse nor errorCallback fires
            // (shouldn't happen, but guarantees the lock can't get stuck forever).
            const t = this
            const lockTimeout = setTimeout(function() {
                if (t._isMutating) {
                    t._isMutating = false
                    if (import.meta.env.DEV) {
                        console.warn('[FlexibleLayouts] operation lock reset by safety timeout', { column: t.column, name })
                    }
                }
            }, 30000)

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
            if (import.meta.env.DEV) {
                console.debug('[FlexibleLayouts] limit counts', { column: t.column, counts: counts })
            }

            MoonShine.request(t, t.url, 'post', {
                field: t.column,
                path: t.flPath,
                name: name,
                counts: counts,
            }, {}, {
                afterResponse: function(data) {
                    clearTimeout(lockTimeout)
                    t._isMutating = false

                    const html = data.blockHtml ?? ''
                    const newIndex = t._directBlocks().length
                    const uid = t._genUid()

                    const wrapper = document.createElement('div')
                    wrapper.className = '_fl-block'
                    wrapper.setAttribute('data-row-key', newIndex)
                    wrapper.setAttribute('data-fl-uid', uid)
                    wrapper.setAttribute('data-correct-type', name)
                    wrapper.innerHTML = html
                    t.blocksContainer.appendChild(wrapper)

                    const tabBtn = document.createElement('button')
                    tabBtn.type = 'button'
                    tabBtn.className = '_fl-tab'
                    tabBtn.setAttribute('role', 'tab')
                    tabBtn.setAttribute('aria-selected', 'false')
                    tabBtn.tabIndex = -1
                    tabBtn.setAttribute('data-orig-idx', newIndex)
                    tabBtn.setAttribute('data-fl-uid', uid)
                    const title = data.blockTitle || (t.blockMeta[name] && t.blockMeta[name].title) || name
                    const iconHtml = (t.blockMeta[name] && t.blockMeta[name].icon) ? '<span class="_fl-tab-icon">' + t.blockMeta[name].icon + '</span>' : ''
                    tabBtn.innerHTML = '<span class="_fl-tab-grip">⠿</span>' + iconHtml + '<span class="_fl-tab-label">' + title + '</span>'
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
                errorCallback: function(errorData) {
                    clearTimeout(lockTimeout)
                    t._isMutating = false
                    console.error('[FlexibleLayouts] add() request failed', { column: t.column, name, error: errorData })
                },
            })
        },

        remove() {
            if (this._isMutating) {
                if (import.meta.env.DEV) {
                    console.warn('[FlexibleLayouts] remove() blocked by operation lock', { column: this.column })
                }
                return
            }

            const block = this.$el.closest('._fl-block')
            if (!block) return

            const name = block.getAttribute('data-correct-type')

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

            const t = this
            this.$nextTick(function() {
                document.dispatchEvent(
                    new CustomEvent('flexible-layouts:block-removed', {
                        bubbles: true,
                        detail: { name: name, column: t.column },
                    }),
                )
            })
        },

        // Duplicate a block: fetch a fresh server-rendered instance of the
        // same block type via store() (correct ids/names + authoritative
        // limit check), then copy field values from the source block into
        // the new markup. The duplicate is inserted right after the source.
        duplicate() {
            if (this._isMutating) {
                if (import.meta.env.DEV) {
                    console.warn('[FlexibleLayouts] duplicate() blocked by operation lock', { column: this.column })
                }
                return
            }

            const sourceBlock = this.$el.closest('._fl-block')
            if (!sourceBlock) return

            const sourceIndex = this._directBlocks().indexOf(sourceBlock)
            if (sourceIndex === -1) return

            const name = sourceBlock.getAttribute('data-correct-type')
            if (!name) return

            this._isMutating = true

            const t = this

            // Safety reset in case neither afterResponse nor errorCallback fires.
            const lockTimeout = setTimeout(function() {
                if (t._isMutating) {
                    t._isMutating = false
                    if (import.meta.env.DEV) {
                        console.warn('[FlexibleLayouts] operation lock reset by safety timeout', { column: t.column, name })
                    }
                }
            }, 30000)

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
            if (import.meta.env.DEV) {
                console.debug('[FlexibleLayouts] limit counts', { column: t.column, counts: counts })
            }

            MoonShine.request(t, t.url, 'post', {
                field: t.column,
                path: t.flPath,
                name: name,
                counts: counts,
            }, {}, {
                afterResponse: function(data) {
                    clearTimeout(lockTimeout)
                    t._isMutating = false

                    const html = data.blockHtml ?? ''
                    const uid = t._genUid()

                    const wrapper = document.createElement('div')
                    wrapper.className = '_fl-block'
                    wrapper.setAttribute('data-fl-uid', uid)
                    wrapper.setAttribute('data-correct-type', name)
                    wrapper.innerHTML = html
                    sourceBlock.after(wrapper)

                    const sourceTab = t.tabBar.querySelectorAll(':scope > ._fl-tab')[sourceIndex]
                    if (sourceTab) {
                        const tabBtn = document.createElement('button')
                        tabBtn.type = 'button'
                        tabBtn.className = '_fl-tab'
                        tabBtn.setAttribute('role', 'tab')
                        tabBtn.setAttribute('aria-selected', 'false')
                        tabBtn.tabIndex = -1
                        tabBtn.setAttribute('data-fl-uid', uid)
                        const title = data.blockTitle || (t.blockMeta[name] && t.blockMeta[name].title) || name
                        const iconHtml = (t.blockMeta[name] && t.blockMeta[name].icon) ? '<span class="_fl-tab-icon">' + t.blockMeta[name].icon + '</span>' : ''
                        tabBtn.innerHTML = '<span class="_fl-tab-grip">⠿</span>' + iconHtml + '<span class="_fl-tab-label">' + title + '</span>'
                        sourceTab.after(tabBtn)
                    }

                    t._copyFieldValues(sourceBlock, wrapper)

                    const newIndex = t._directBlocks().indexOf(wrapper)
                    t.switchTab(newIndex)
                    t.resolveReindex()

                    t.$nextTick(function() {
                        document.dispatchEvent(
                            new CustomEvent('flexible-layouts:block-duplicated', {
                                bubbles: true,
                                detail: { name: name, column: t.column, sourceIndex: sourceIndex },
                            }),
                        )
                    })
                },
                errorCallback: function(errorData) {
                    clearTimeout(lockTimeout)
                    t._isMutating = false
                    console.error('[FlexibleLayouts] duplicate() request failed', { column: t.column, name, error: errorData })
                },
            })
        },

        // Pairwise copy of form values between two same-type blocks
        // (source -> target). Same block type renders the same field order,
        // so DOM-order pairing is deterministic. File inputs are skipped —
        // browsers forbid setting input[type=file] programmatically.
        _copyFieldValues(sourceBlock, targetBlock) {
            const selector = 'input:not([type=file]), select, textarea'
            const from = sourceBlock.querySelectorAll(selector)
            const to = targetBlock.querySelectorAll(selector)

            if (from.length !== to.length) {
                if (import.meta.env.DEV) {
                    console.warn('[FlexibleLayouts] duplicate() field count mismatch — copying min pair set', {
                        column: this.column,
                        sourceCount: from.length,
                        targetCount: to.length,
                    })
                }
            }

            const fileSkipped = sourceBlock.querySelectorAll('input[type=file]').length
            if (fileSkipped > 0 && import.meta.env.DEV) {
                console.warn('[FlexibleLayouts] duplicate() skipped file inputs — they cannot be copied', { column: this.column, count: fileSkipped })
            }

            const count = Math.min(from.length, to.length)
            for (let i = 0; i < count; i++) {
                const src = from[i]
                const dst = to[i]

                if (src.tagName === 'SELECT') {
                    dst.value = src.value
                    dst.dispatchEvent(new Event('change', { bubbles: true }))
                    continue
                }

                if (src.type === 'checkbox' || src.type === 'radio') {
                    dst.checked = src.checked
                    dst.dispatchEvent(new Event('change', { bubbles: true }))
                    continue
                }

                dst.value = src.value
                if (src.type !== 'hidden') {
                    dst.dispatchEvent(new Event('input', { bubbles: true }))
                }
            }
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

        moveTab(step) {
            const tabs = Array.from(this.tabBar.querySelectorAll(':scope > ._fl-tab'))
            if (!tabs.length) return

            const next = (this.activeTab + step + tabs.length) % tabs.length
            this.switchTab(next)
            tabs[next].focus()
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
                tab.setAttribute('aria-selected', i === t.activeTab ? 'true' : 'false')
                tab.tabIndex = i === t.activeTab ? 0 : -1
            })
        },

        syncBlockOrder() {
            const tabs = Array.from(this.tabBar.querySelectorAll(':scope > ._fl-tab'))
            const t = this

            // Use DocumentFragment to batch DOM moves into a single reflow.
            const fragment = document.createDocumentFragment()
            tabs.forEach(function(tab) {
                const uid = tab.dataset.flUid
                if (uid) {
                    const block = t.blocksContainer.querySelector(':scope > ._fl-block[data-fl-uid="' + uid + '"]')
                    if (block) {
                        fragment.appendChild(block)
                    }
                }
            })
            t.blocksContainer.appendChild(fragment)
        },
    }))
})
