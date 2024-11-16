import { popup } from '../utils/utils'

export default (di, container) => {
    $('a.popup', container).click(function () {
        popup($(this).attr('href'), this)
        return false
    })
}
