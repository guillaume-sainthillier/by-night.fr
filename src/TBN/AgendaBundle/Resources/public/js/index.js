$(function ()
{
    init_soirees();
});

function init_soirees(selector)
{
    init_unveil(selector);
}

/**
 * Initialise le lazy loading des images
 * @param {type} selecteur
 * @returns {undefined}
 */
function init_unveil(selecteur)
{
    $(".img", selecteur || document).unveil(200, function ()
    {
        $(this).removeClass("loading");
    });
}