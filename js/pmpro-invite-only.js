jQuery(document).ready( function( $ ) {
    "use strict";

    var pmproio = {
        init: function() {
            this.user_required = $('.pmproio-setting_give-to-member' );
            this.code_uses = $('.pmproio-setting_code-uses');
            this.code_count = $('.pmproio-setting_code-count');
            this.code_uses_row = this.code_uses.closest( 'tr.pmproio-settings-row');
            this.code_count_row = this.code_count.closest( 'tr.pmproio-settings-row');

            this.code_uses_row.hide();
            this.code_count_row.hide();

            var self = this;

            self.user_required.unbind('click').on('click', function() {
               self.code_uses_row.toggle();
            });

            self.code_uses.on('keyup', function() {
                if ( 0 < self.code_uses.val() ) {
                    self.code_count_row.show();
                } else {
                    self.code_count_row.hide();
                }
            });
        }
    };

    pmproio.init();
});