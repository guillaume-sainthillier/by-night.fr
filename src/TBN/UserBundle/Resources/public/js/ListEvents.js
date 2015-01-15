var ListEvents = {
    init: function ()
    {
        $(function ()
        {
            $('.brouillon').click(function()
            {
                var that = $(this);
                
                that.attr('disabled', true);
                $.post(that.data('href'), {
                    brouillon: !that.prop('checked')
                }).done(function()
                {
                    that.attr('disabled', false);
                });
            });
            
            $('.annuler').click(function()
            {
                var that = $(this);
                
                that.attr('disabled', true);
                $.post(that.data('href'), {
                    annuler: that.prop('checked')
                }).done(function()
                {
                    that.attr('disabled', false);
                });
            });
        });
    }
};

ListEvents.init();