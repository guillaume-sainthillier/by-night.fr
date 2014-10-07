/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Initialise les datepicker
 * @param {jQuery|document} selecteur le selecteur pour le filtrage
 * @returns {void}
 */
function init_datepicker(selecteur)
{
    $('.datepicker', selecteur || document).each(function()
    {
        $(this).datepicker();
    });
}
/**
 * Initialise les select picker
 * @param {jQuery|document} selecteur le selecteur pour le filtrage
 * @returns {void}
 */
function init_selectpicker(selecteur)
{
    $('select', selecteur || document).each(function()
    {
        $(this).selectpicker();
    });
}
