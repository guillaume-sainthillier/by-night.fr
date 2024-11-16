import $ from 'jquery'

export default (di, container) => {
    $('[data-bs-toggle="tooltip"]', container).tooltip()
}
