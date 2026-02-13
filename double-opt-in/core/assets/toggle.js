if (typeof ToggleButton !== "function") {
    /**
     * Create the Toggle Button
     * @param dom
     * @constructor
     */
    function ToggleButton(dom, id) {
        this.dom = dom;

        this.setId = function(id){
            this.id = 'f12-toggle-id-'+id;
            this.id = this.id.replace(new RegExp('\\[', 'g'), '');
            this.id = this.id.replace(new RegExp('\\]', 'g'), '');
        }

        this.setId(id);

        this.init = function () {
            var name = this.dom.attr('name');

            var before = this.dom.attr('data-before');
            var after = this.dom.attr('data-after');

            this.dom.wrap('<div class="f12-toggle"></div>');
            this.dom.parent().append('<button type="button" id="' + this.id + '" class="btn btn-toggle" data-before="' + before + '" data-after="' + after + '" data-switch-target="' + name + '" aria-pressed="false"><span class="handle"></span></button>');

            if (parseInt(this.dom.val()) === 1) {
                this.updateStatus(1);
            }
        };

        this.updateStatus = function (value) {
            this.dom.val(value);
            this.dom.attr('value', value);

            // Change button status
            if (value === 1) {
                this.dom.parent().find('button').addClass('active');
                this.dom.parent().find('button').attr('aria-pressed', 'true');
            } else {
                this.dom.parent().find('button').removeClass('active');
                this.dom.parent().find('button').attr('aria-pressed', 'false')
            }
        }

        this.onClick = function (target) {
            var targetObject = 'input[name="' + target + '"]';
            var value = parseInt(jQuery(document).find(targetObject).val());

            if (value === 1) {
                value = 0;
            } else {
                value = 1;
            }

            this.updateStatus(value);
        }

        this.addEventHandler = function () {
            var c = this;
            jQuery(document).on('click', '#' + this.id, function () {
                c.onClick(jQuery(this).attr('data-switch-target'));
            });

            // Handle clicks on labels within the toggle wrapper
            var $wrapper = this.dom.closest('.f12-checkbox-toggle');
            if ($wrapper.length) {
                $wrapper.on('click', function (e) {
                    // Skip if clicking the toggle button itself
                    if (jQuery(e.target).closest('.btn-toggle').length) {
                        return;
                    }
                    e.preventDefault();
                    c.onClick(c.dom.attr('name'));
                });
            }
        }

        this.init();
        this.addEventHandler();
    }

    jQuery.fn.f12Toggle = function () {

        this.each(function () {
            var name = jQuery(this).attr('name');
            new ToggleButton(jQuery(this), name);
        });
    }

    jQuery(document).ready(function () {
        jQuery('input[type="hidden"].toggle').f12Toggle();
    });
}