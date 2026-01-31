/** @jsx h */
import { Fragment, h, render } from 'preact'
import { autocomplete } from '@algolia/autocomplete-js'
import '@algolia/autocomplete-theme-classic'
import { createLocalStorageRecentSearchesPlugin } from '@algolia/autocomplete-plugin-recent-searches'
import groupBy from 'lodash/groupBy'
import hotkeys from 'hotkeys-js'
import $ from 'jquery'
import ChevronRightIcon from '@/js/icons/lucide/ChevronRight'
import CrosshairIcon from '@/js/icons/lucide/Crosshair'
import DramaIcon from '@/js/icons/lucide/Drama'
import SearchIcon from '@/js/icons/lucide/Search'
import Trash2Icon from '@/js/icons/lucide/Trash2'
import TriangleAlertIcon from '@/js/icons/lucide/TriangleAlert'
import UserIcon from '@/js/icons/lucide/User'

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
        translations: {
            detachedCancelButtonText: 'Annuler',
        },
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
                            <TriangleAlertIcon className="icon-3x text-danger" />
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
                        <SearchIcon width="40" height="40" className="text-muted" />
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

            const request = $.ajax({
                url: globalSearchUrl,
                method: 'GET',
                data: { q: query },
                headers: {
                    Accept: 'application/ld+json',
                },
                dataType: 'json',
            })

            const results = new Promise((resolve, reject) => {
                request
                    .then((response) => {
                        setContext({ error: null })
                        // Extract results from API Platform's 'member' array
                        resolve(response.member || [])
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

const TYPE_ICONS = {
    events: DramaIcon,
    cities: CrosshairIcon,
    users: UserIcon,
}

function TypeIcon({ type, className = '' }) {
    const IconComponent = TYPE_ICONS[type] || SearchIcon
    return <IconComponent className={`icon ${className}`} />
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
                    <TypeIcon type={item.type} />
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
                        <Trash2Icon width="20" height="20" />
                    </button>
                )}
                <button
                    className="aa-ItemActionButton aa-DesktopOnly aa-ActiveOnly"
                    type="button"
                    title="Sélectionner"
                    aria-label="Sélectionner"
                >
                    <ChevronRightIcon width="20" height="20" />
                </button>
            </div>
        </a>
    )
}
