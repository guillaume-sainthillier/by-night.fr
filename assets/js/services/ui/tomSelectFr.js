// tom-select only ships English strings: the French ones every instance shares

export const render = {
    option_create: (data, escape) => `<div class="create">Ajouter <strong>${escape(data.input)}</strong>…</div>`,
    no_results: () => '<div class="no-results">Aucun résultat</div>',
}

export const plugins = {
    remove_button: { title: 'Retirer' },
}
