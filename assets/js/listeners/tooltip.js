import $ from 'jquery'

export default (_di, container) => {
    $('[data-bs-toggle="tooltip"]', container).tooltip()
}
