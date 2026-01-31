import $ from 'jquery'
import { create as createDatepicker } from '@/js/services/ui/DatepickerService'
import { create as createFancybox } from '@/js/services/ui/FancyboxService'

$(document).ready(function () {
    document.querySelectorAll('input.shorcuts_date').forEach((el) => {
        createDatepicker({
            element: el,
            fromInput: document.getElementById(el.dataset.from),
            toInput: document.getElementById(el.dataset.to),
            singleDate: el.dataset.singleDate === 'true',
            ranges: el.dataset.ranges ? JSON.parse(el.dataset.ranges) : {},
        })
    })

    document.querySelectorAll('.image-gallery').forEach((el) => {
        createFancybox({ element: el })
    })
})
