// tom-select only ships English strings: the French ones every instance shares

import type { TomOption } from 'tom-select/dist/esm/types/core.js'

export const render = {
    option_create: (data: TomOption, escape: (text: string) => string) =>
        `<div class="create">Ajouter <strong>${escape(data.input)}</strong>…</div>`,
    no_results: () => '<div class="no-results">Aucun résultat</div>',
}

export const plugins = {
    remove_button: { title: 'Retirer' },
}
