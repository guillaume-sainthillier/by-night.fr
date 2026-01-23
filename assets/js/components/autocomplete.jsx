/** @jsx h */
import { Fragment, h, render } from 'preact'
import { autocomplete } from '@algolia/autocomplete-js'
import '@algolia/autocomplete-theme-classic'
import { createLocalStorageRecentSearchesPlugin } from '@algolia/autocomplete-plugin-recent-searches'
import groupBy from 'lodash/groupBy'
import hotkeys from 'hotkeys-js'
import $ from 'jquery'

export default function init({
    autocompleteSelector = '#autocomplete',
    container = document,
    searchPlaceholder = 'Recherche globale',
    inputPlaceholder = 'Rechercher des événements, villes, membres...',
    globalSearchUrl = null,
    searchPageUrl = null,
    enableHotkeys = true,
}) {
    const $autocomplete = $(autocompleteSelector, container)
    if ($autocomplete.length === 0 || !$autocomplete.is(':visible')) {
        return
    }

    const recentSearchesPlugin = createLocalStorageRecentSearchesPlugin({
        key: `${autocompleteSelector}-v2`,

        transformSource({ source, onRemove }) {
            return {
                ...source,
                getItems(params) {
                    return source.getItems(params)
                },
                getItemUrl({ item }) {
                    return item.url
                },
                onSelect({ event, state }) {
                    if (state.status === 'loading') {
                        event.preventDefault()
                    }
                },
                templates: {
                    ...source.templates,
                    header() {
                        return (
                            <Fragment>
                                <span className="aa-SourceHeaderTitle">Recherches récentes</span>
                                <div className="aa-SourceHeaderLine" />
                            </Fragment>
                        )
                    },
                    item({ item }) {
                        return <ResultItem item={item} onRemove={onRemove} />
                    },
                },
            }
        },
    })

    const { addItem: addRecentSearchItem } = recentSearchesPlugin.data

    const { destroy } = autocomplete({
        container: $autocomplete[0],
        placeholder: inputPlaceholder,
        openOnFocus: true,
        defaultActiveItemId: 0,
        detachedMediaQuery: '',
        plugins: [recentSearchesPlugin],
        render({ children, state }, root) {
            render(
                <Fragment>
                    {children}
                    {state.query && state.status === 'idle' && state.collections.length > 0 && searchPageUrl && (
                        <div className="aa-PanelFooter">
                            <a href={`${searchPageUrl}?q=${encodeURIComponent(state.query)}`} className="aa-ViewAllLink">
                                Voir tous les résultats pour "<strong>{state.query}</strong>"
                            </a>
                        </div>
                    )}
                </Fragment>,
                root
            )
        },
        renderNoResults({ state }, root) {
            const { error } = state.context

            if (state.status === 'loading') {
                return render(
                    <div className="aa-PanelLayout">
                        <div className="d-flex flex-column justify-content-center align-items-center text-center p-4">
                            <div className="spinner-border text-primary" role="status">
                                <span className="visually-hidden">Chargement...</span>
                            </div>
                            <h2 className="mt-4 h5">Recherche en cours...</h2>
                        </div>
                    </div>,
                    root
                )
            }

            if (error) {
                return render(
                    <div className="aa-PanelLayout">
                        <div className="d-flex flex-column justify-content-center align-items-center text-center p-4">
                            <i className={"icon fa fa-exclamation-triangle fa-3x text-danger"}></i>
                            <h2 className="mt-4 h5 text-danger">Erreur de recherche</h2>
                            <p className="text-muted">
                                Une erreur est survenue lors de la recherche. Veuillez réessayer dans quelques
                                instants.
                            </p>
                        </div>
                    </div>,
                    root
                )
            }

            if (!state.query || state.status !== 'idle') {
                return
            }

            return render(
                <div className="aa-PanelLayout">
                    <div className="d-flex flex-column justify-content-center align-items-center text-center p-4">
                        <svg
                            viewBox="0 0 20 20"
                            fill="none"
                            width="40"
                            height="40"
                            className="stroke-current text-muted"
                        >
                            <path d="M15.5 4.8c2 3 1.7 7-1 9.7h0l4.3 4.3-4.3-4.3a7.8 7.8 0 01-9.8 1m-2.2-2.2A7.8 7.8 0 0113.2 2.4M2 18L18 2" />
                        </svg>
                        <h2 className="mt-4 display-4">
                            Aucun résultat pour <strong>"{state.query}"</strong>.
                        </h2>
                        {searchPageUrl && (
                            <a href={`${searchPageUrl}?q=${encodeURIComponent(state.query)}`} className="btn btn-primary mt-3">
                                Essayer la recherche avancée
                            </a>
                        )}
                    </div>
                </div>,
                root
            )
        },
        getSources({ query, setContext }) {
            if (!query) {
                return []
            }

            const request = $.get(globalSearchUrl, { query }, null, 'json')
            const results = new Promise((resolve, reject) => {
                request
                    .then((results) => {
                        setContext({ error: null })
                        resolve(results)
                    })
                    .catch((error) => {
                        console.error('Search API error:', error)
                        setContext({ error: true })
                        reject(error)
                    })
            })

            return results
                .then((results) => {
                    const sources = []
                    const groupedResults = groupBy(results, 'type')
                    for (const [type, results] of Object.entries(groupedResults)) {
                        const firstResult = results[0]
                        sources.push({
                            sourceId: type,
                            getItemUrl({ item }) {
                                return item.url
                            },
                            getItems() {
                                return results
                            },
                            onSelect({ item }) {
                                addRecentSearchItem(item)
                            },
                            templates: {
                                header() {
                                    return (
                                        <Fragment>
                                            <span className="aa-SourceHeaderTitle">{firstResult.category}</span>
                                            <div className="aa-SourceHeaderLine" />
                                        </Fragment>
                                    )
                                },
                                item({ item }) {
                                    return <ResultItem item={item} />
                                },
                            },
                        })
                    }

                    return sources
                })
                .catch(() => {
                    // Return empty array on error, error state is handled in renderNoResults
                    return []
                })
        },
    })

    const isMacOS = /(Mac|iPhone|iPod|iPad)/i.test(navigator.platform) || navigator.appVersion.indexOf('Mac') !== -1
    const $autocompleteBtn = $autocomplete.find('.aa-Autocomplete button')

    $autocompleteBtn.find('.aa-DetachedSearchButtonPlaceholder').text(searchPlaceholder)

    if (enableHotkeys) {
        const kbd = $('<kbd>')
        if (isMacOS) {
            kbd.text(`Cmd + K`)
        } else {
            kbd.text(`Ctrl + K`)
        }
        kbd.appendTo($autocompleteBtn)

        hotkeys('ctrl+k,cmd+k', (event) => {
            event.preventDefault()
            $autocompleteBtn.click()
        })
    }

    return {
        unload() {
            if (enableHotkeys) {
                hotkeys.unbind('ctrl+k,cmd+k')
            }
        },
        teardown: destroy,
    }
}

function Icon({ icon, className = '' }) {
    if (!icon) {
        return null
    }
    if (icon.startsWith('http') || icon.startsWith('data:image') || icon.startsWith('blob:') || icon.startsWith('/')) {
        return <img src={icon} className={`icon ${className}`} alt="icon" />
    }
    return <i className={`icon ${icon} ${className}`} />
}

function HighlightedText({ item, attribute }) {
    const highlightedValue = item.highlightResult?.[attribute]?.value || item[attribute] || ''

    // Convert __aa-highlight__ tags to <mark> tags for styling
    const html = highlightedValue
        .replace(/__aa-highlight__/g, '<mark class="aa-HighlightedText p-0">')
        .replace(/__\/aa-highlight__/g, '</mark>')

    return <span dangerouslySetInnerHTML={{ __html: html }} />
}

function ResultItem({ item, onRemove }) {
    return (
        <a href={item.url} className="aa-ItemLink">
            <div className="aa-ItemContent">
                <div className="aa-ItemIcon">
                    <Icon icon={item.icon} />
                </div>
                <div className="aa-ItemContentBody">
                    <div className="aa-ItemContentTitle">
                        <HighlightedText item={item} attribute="label" />
                        {item.shortDescription && (
                            <small className="text-muted ms-2">
                                <HighlightedText item={item} attribute="shortDescription" />
                            </small>
                        )}
                    </div>
                    {item.description && <div className="aa-ItemContentDescription">{item.description}</div>}
                </div>
            </div>
            <div className="aa-ItemActions">
                {onRemove && (
                    <button
                        type="button"
                        className="aa-ItemActionButton aa-DesktopOnly"
                        title="Supprimer cette recherche"
                        aria-label="Supprimer cette recherche"
                        onClick={(event) => {
                            event.preventDefault()
                            event.stopPropagation()
                            onRemove(item.id)
                        }}
                    >
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
                            <path d="M18 7v13c0 0.276-0.111 0.525-0.293 0.707s-0.431 0.293-0.707 0.293h-10c-0.276 0-0.525-0.111-0.707-0.293s-0.293-0.431-0.293-0.707v-13zM17 5v-1c0-0.828-0.337-1.58-0.879-2.121s-1.293-0.879-2.121-0.879h-4c-0.828 0-1.58 0.337-2.121 0.879s-0.879 1.293-0.879 2.121v1h-4c-0.552 0-1 0.448-1 1s0.448 1 1 1h1v13c0 0.828 0.337 1.58 0.879 2.121s1.293 0.879 2.121 0.879h10c0.828 0 1.58-0.337 2.121-0.879s0.879-1.293 0.879-2.121v-13h1c0.552 0 1-0.448 1-1s-0.448-1-1-1zM9 5v-1c0-0.276 0.111-0.525 0.293-0.707s0.431-0.293 0.707-0.293h4c0.276 0 0.525 0.111 0.707 0.293s0.293 0.431 0.293 0.707v1zM9 11v6c0 0.552 0.448 1 1 1s1-0.448 1-1v-6c0-0.552-0.448-1-1-1s-1 0.448-1 1zM13 11v6c0 0.552 0.448 1 1 1s1-0.448 1-1v-6c0-0.552-0.448-1-1-1s-1 0.448-1 1z" />
                        </svg>
                    </button>
                )}
                <button
                    className="aa-ItemActionButton aa-DesktopOnly aa-ActiveOnly"
                    type="button"
                    title="Sélectionner"
                    aria-label="Sélectionner"
                >
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
                        <path d="M18.984 6.984h2.016v6h-15.188l3.609 3.609-1.406 1.406-6-6 6-6 1.406 1.406-3.609 3.609h13.172v-4.031z" />
                    </svg>
                </button>
            </div>
        </a>
    )
}
