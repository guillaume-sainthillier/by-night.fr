import type { App } from '@silarhi/app-js'
import { findAll, findOne, on } from '@silarhi/app-js'
import type { FormView } from '@silarhi/app-js/form'
import type { EventForm } from '@/js/forms/EventForm'
import { plural } from '@/js/utils/plural'

/**
 * The "Une date" / "Plusieurs dates" switch of the event form: sets the mode on the dates card, whose stylesheet hides
 * the generator and the list of dates for a single date. Going back to a single date empties the list, once the member
 * agrees, since its hidden dates would still be sent.
 */
export default function initEventDateMode(form: FormView<EventForm>, container: ParentNode, app: App) {
    const card = findOne('[data-date-mode]', container)
    const collection = form.get('timesheets')
    if (!card) return

    const choices = findAll('[data-date-mode-choice]', card)
    const select = (mode: string | undefined) => {
        card.dataset.dateMode = mode
        for (const choice of choices) {
            const selected = choice.dataset.dateModeChoice === mode
            choice.classList.toggle('active', selected)
            choice.setAttribute('aria-pressed', selected ? 'true' : 'false')
        }
    }

    const choose = async (mode: string | undefined) => {
        if (mode === card.dataset.dateMode) return

        const count = collection.children.length
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

            await app.get('collectionManager').clear(collection)
        }

        select(mode)
    }

    for (const choice of choices) {
        on(choice, 'click', () => choose(choice.dataset.dateModeChoice))
    }
}
