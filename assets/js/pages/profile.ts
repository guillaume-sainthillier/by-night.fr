import type { Page } from '@silarhi/app-js'
import { Tab } from '@tabler/core'
import SocialLogin from '@/js/components/SocialLogin'
import { create as createAutocomplete } from '@/js/services/ui/AutocompleteService'

const initialize: Page = () => {
    new SocialLogin().init()

    initTabs()
    initDeleteConfirmation()
    initAvatarPreview()
    initCityPicker()
}

// The tabs follow the URL: a link to #password opens that tab (the failed deletion redirects to #delete), and the
// open tab survives a reload
function initTabs() {
    const showTab = (hash: string) => {
        const trigger = document.querySelector<HTMLElement>(`.account-tabs [href="${CSS.escape(hash)}"]`)
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
            window.history.replaceState(null, '', trigger.getAttribute('href'))
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
    const confirmation = document.querySelector<HTMLInputElement>('[data-delete-confirmation]')
    const submit = document.querySelector<HTMLButtonElement>('[data-delete-submit]')
    if (!confirmation || !submit) {
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
    const input = preview?.closest('.card-body')?.querySelector<HTMLInputElement>('input[type="file"]')
    if (!preview || !input) {
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
        const element = picker.querySelector<HTMLInputElement>('input[type="text"]')
        const url = picker.dataset.cityPicker
        if (!element || !url) {
            continue
        }

        createAutocomplete({
            element,
            valueInput: picker.querySelector<HTMLInputElement>('input[type="hidden"]'),
            url,
        })
    }
}

window.App.registerPage('profile', initialize)
