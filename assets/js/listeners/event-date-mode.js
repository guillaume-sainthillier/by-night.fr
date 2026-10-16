import { findAll, findOne, on } from '@/js/utils/dom'
import { plural } from '@/js/utils/plural'

/**
 * The "Une date" / "Plusieurs dates" switch of the event form: sets the mode on the dates card, whose stylesheet hides
 * the generator and the list of dates for a single date. Going back to a single date empties the list, once the member
 * agrees, since its hidden dates would still be sent.
 *
 * @param {HTMLElement} container - DOM container
 * @param {App} app
 */
export default function initEventDateMode(container, app) {
    const card = findOne('[data-date-mode]', container)
    const collection = findOne('#app_event_timesheets', container)
    if (!card || !collection) return

    const choices = findAll('[data-date-mode-choice]', card)
    const select = (mode) => {
        card.dataset.dateMode = mode
        for (const choice of choices) {
            const selected = choice.dataset.dateModeChoice === mode
            choice.classList.toggle('active', selected)
            choice.setAttribute('aria-pressed', selected ? 'true' : 'false')
        }
    }

    const choose = async (mode) => {
        if (mode === card.dataset.dateMode) return

        const count = findAll('.collection-item', collection).length
        if (mode === 'single' && count > 0) {
            const confirmed = await app.get('modalManager').createConfirm({
                title: 'Garder une seule date ?',
                text: plural(count, {
                    one: 'La date de la liste sera retirée. La période et les horaires restent.',
                    other: 'Les # dates de la liste seront retirées. La période et les horaires restent.',
                }),
                confirmButtonText: 'Retirer les dates',
            })
            if (!confirmed) return

            app.get('collectionManager').emptyCollection(collection)
        }

        select(mode)
    }

    for (const choice of choices) {
        on(choice, 'click', () => choose(choice.dataset.dateModeChoice))
    }
}
