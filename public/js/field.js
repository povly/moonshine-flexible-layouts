document.addEventListener('alpine:init', () => {
    Alpine.data('flexibleLayouts', (url, column) => ({
        url: url,
        column: column,
        root: null,
        blocksContainer: null,
        init() {
            this.root = this.$root
            this.blocksContainer = this.root.querySelector('._flexible-blocks')
            this._reindex()
            const t = this

            MoonShine.iterable.sortable(
                this.blocksContainer,
                null,
                'flexibleLayouts',
                null,
                {
                    handle: '.handle'
                },
                function(evt) {
                    t._reindex()
                }
            )
        },
        add(name) {
            const t = this

            let blocksCount = {}
            const types = document.querySelectorAll('._type-value')
            types.forEach(function(l) {
                blocksCount[l.value] = blocksCount[l.value] ? blocksCount[l.value]+1 : 1
            })

            MoonShine.request(t, t.url, 'post', {
                field: t.column,
                name: name,
                counts: blocksCount
            }, {}, {
                afterResponse: function(data) {
                    const tempContainer = document.createElement('div');
                    tempContainer.innerHTML = data.html ?? data.htmlData[0].html ?? '';

                    while (tempContainer.firstChild) {
                        t.blocksContainer.appendChild(tempContainer.firstChild);
                    }

                    t._reindex()

                    t.$nextTick(function () {
                        document.dispatchEvent(
                            new CustomEvent('flexible-layouts:block-added', {
                                bubbles: true,
                                detail: { name: name, column: t.column },
                            }),
                        );
                    })
                }
            })
        },
        remove() {
            this.$el.closest('._flexible-block').remove()
            this._reindex()
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
