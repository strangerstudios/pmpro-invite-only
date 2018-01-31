jQuery(document).ready( function( $ ) {
    "use strict";

    var pmproio = {
        init: function() {
            this.member_codes = $('.pmproio-setting_give-to-member' );
            this.code_uses = $('.pmproio-setting_code-uses');
            this.code_count = $('.pmproio-setting_code-count');
            this.code_uses_row = this.code_uses.closest( 'tr.pmproio-settings-row');
            this.code_count_row = this.code_count.closest( 'tr.pmproio-settings-row');

            var self = this;

            self.member_codes.unbind('click').on('click', function() {
               if ( self.member_codes.is(':checked') ) {
                   self.code_uses_row.show();
               } else {
                   self.code_uses.val(1);
                   self.code_count.val(null);
                   self.code_uses_row.hide();
                   self.code_count_row.hide();
               }
            });

            self.code_uses.on( 'keyup blur change', function() {
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