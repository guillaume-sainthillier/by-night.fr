import TomSelect from 'tom-select'
import '@/scss/lazy-components/_selects.scss'
import '@/scss/lazy-components/_tags.scss'

export default (container = document) => {
    container.querySelectorAll('.js-tags-input:not(.tomselected)').forEach((el) => {
        const tagsUrl = el.dataset.tagsUrl
        const allowNew = el.dataset.tagsAllowNew === 'true'
        const maxItems = parseInt(el.dataset.tagsMaxItems, 10) || null
        const separator = el.dataset.tagsSeparator || ','
        const placeholder = el.getAttribute('placeholder') || ''

        const options = {
            delimiter: separator,
            persist: false,
            create: allowNew,
            maxItems: maxItems,
            placeholder: placeholder,
            plugins: ['remove_button'],
            render: {
                no_results() {
                    return '<div class="no-results">Aucun r\u00e9sultat</div>'
                },
            },
        }

        if (tagsUrl) {
            options.valueField = 'text'
            options.labelField = 'text'
            options.searchField = 'text'
            options.load = function (query, callback) {
                fetch(`${tagsUrl}?q=${encodeURIComponent(query)}`, {
                    headers: { Accept: 'application/ld+json' },
                })
                    .then((res) => res.json())
                    .then((data) => {
                        const items = data.member || data.results || []
                        callback(
                            items.map((item) => ({
                                text: item.text || item.id,
                            }))
                        )
                    })
                    .catch(() => callback())
            }
        }

        new TomSelect(el, options)
    })
}
