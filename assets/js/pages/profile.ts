import { definePage } from '@silarhi/app-js'
import { formView } from '@silarhi/app-js/form'
import { Tab } from '@tabler/core'
import SocialLogin from '@/js/components/SocialLogin'
import { path } from '@/js/router'
import { create as createAutocomplete } from '@/js/services/ui/AutocompleteService'

export default definePage(() => {
    new SocialLogin().init()

    initTabs()
    initDeleteConfirmation()
    initAvatarPreview()
    initCityPicker()
})

// The tabs follow the URL: a link to #password opens that tab (the failed deletion redirects to #delete), and the
// open tab survives a reload
function initTabs() {
    const showTab = (hash: string) => {
        // The tabs are Tabler:SegmentedControl buttons, which name their pane in data-bs-target
        const trigger = document.querySelector<HTMLElement>(`.account-tabs [data-bs-target="${CSS.escape(hash)}"]`)
        if (trigger) {
            const tab = Tab.getOrCreateInstance(trigger)
            if (tab instanceof Tab) {
                tab.show()
            }
        }
    }

    if (window.location.hash) {
        showTab(window.location.hash)
    }

    for (const trigger of document.querySelectorAll('.account-tabs [data-bs-toggle="tab"]')) {
        trigger.addEventListener('shown.bs.tab', () => {
            window.history.replaceState(null, '', trigger.getAttribute('data-bs-target'))
        })
    }

    // Links to a tab inside the panes ("Annuler et revenir au profil")
    for (const link of document.querySelectorAll('[data-account-tab]')) {
        link.addEventListener('click', (event) => {
            event.preventDefault()
            showTab(link.getAttribute('href') ?? '')
            document.querySelector('.account-tabs')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' })
        })
    }
}

// The deletion button waits for the confirmation word, which the server checks again
function initDeleteConfirmation() {
    const submit = document.querySelector<HTMLButtonElement>('[data-delete-submit]')
    // DeleteAccountFormType's confirmation, read through the view of the button's form
    const confirmation = submit?.form ? formView(submit.form).find('confirmation')?.field().element : null
    if (!submit || !(confirmation instanceof HTMLInputElement)) {
        return
    }

    const update = () => {
        submit.disabled = confirmation.value.trim() !== confirmation.dataset.deleteConfirmation
    }

    confirmation.addEventListener('input', update)
    update()
}

// The chosen picture replaces the current one before the form is saved
function initAvatarPreview() {
    const preview = document.querySelector('[data-avatar-preview]')
    // The file of the picture field (VichImageType) the preview's card holds, read through its form view
    const body = preview?.closest('.card-body')
    const input = body ? formView(body).find('file')?.field().element : null
    if (!preview || !(input instanceof HTMLInputElement)) {
        return
    }

    input.addEventListener('change', () => {
        const file = input.files?.[0]
        if (!file?.type.startsWith('image/')) {
            return
        }

        const image = document.createElement('img')
        image.src = URL.createObjectURL(file)
        image.alt = ''
        image.style.objectFit = 'cover'
        preview.replaceChildren(image)
    })
}

// "Ma ville" (CityPickerType): choosing a city in the list fills the hidden slug, typing again empties it
function initCityPicker() {
    for (const picker of document.querySelectorAll<HTMLElement>('[data-city-picker]')) {
        // The picker holds the fields of its CityPickerType: the city's name, and its slug
        const city = formView(picker)
        const element = city.field('name').element
        const slug = city.field('slug').element
        if (!(element instanceof HTMLInputElement)) {
            continue
        }

        createAutocomplete({
            element,
            valueInput: slug instanceof HTMLInputElement ? slug : null,
            url: (q) => path('api_cities', { _query: { q } }),
        })
    }
}
