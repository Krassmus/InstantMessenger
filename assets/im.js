STUDIP.IM = {
    process_data: function (data) {
        STUDIP.IM.processBuddies(data.buddies);
        STUDIP.IM.processMessages(data.messages);
        STUDIP.IM.resize();
    },
    grosseZahl: 100000000,
    pageTitle: null,
    processBuddies: function (buddies) {
        jQuery("#messenger_contacts > a").removeClass("buddy");
        jQuery.each(buddies, function (index, buddy) {
            if (jQuery("#messenger_contacts > ." + buddy.user_id).length === 0) {
                jQuery("#messenger_contacts").append(
                    jQuery('<a id="messenger_buddy_' + buddy.user_id + '"/>')
                        .html(buddy.avatar)
                        .addClass("buddy")
                        .addClass(buddy.user_id)
                        .hide()
                );
                jQuery("#messenger_contacts > a.buddy:hidden").delay(1500).show();
            } else {
                jQuery("#messenger_contacts > a." + buddy.user_id).addClass("buddy");
            }
        });
        //alle Buddys löschen, die nicht mehr online sind.
        jQuery("#messenger_contacts > a:not(.buddy)").each(function () {
            jQuery(this).remove();
        });
        if (buddies.length === 0) {
            if (!jQuery("#nobody_online").is(":visible")) {
                jQuery("#nobody_online").show();
            }
        } else {
            if (jQuery("#nobody_online").css("display") !== "none") {
                jQuery("#nobody_online").hide();
            }
        }
    },
    processMessages: function (messages) {
        var length = 0;
        jQuery.each(messages, function (discussion_id, message_stream) {
            jQuery.each(message_stream.stream, function (index, message) {
                if (message['new']) {
                    length += 1;
                    if (!jQuery("#messenger_discussion_" +discussion_id).is(":visible")) {
                        jQuery("#messenger_discussion_head_" +discussion_id).addClass("unread");
                    }
                }
            });
        });
        news_icon = jQuery('#barTopMenu a[href^="sms_box.php"] span');
        if (length > 0) {
            news_icon.addClass("new");
            window.document.title = "(!) " + STUDIP.IM.pageTitle;
        } else {
            news_icon.removeClass("new");
            window.document.title = STUDIP.IM.pageTitle;
        }
        if (!STUDIP.IM.messengerIsOpen() && length > 0) {
            //Balken links aufleuchten lassen:
            if (jQuery("#messenger_drag").queue("fx").length < 4) {
                jQuery("#messenger_drag")
                    .delay(100)
                    .animate({'background-color': "#ff3333"}, 250)
                    .delay(100)
                    .animate({'background-color': "#ddeeff"}, 250);
            }
        } else {
            if (STUDIP.IM.messengerIsOpen()) {
                var id = jQuery('div[id^="messenger_discussion_"]:visible').attr("id");
                if (id) {
                    var discussion_id = id.substr(id.lastIndexOf("_") + 1);
                    STUDIP.IM.mark_the_read(discussion_id);
                }
            }
        }
        jQuery.each(messages, function (user_id, moremessages) {
            user_id = user_id.replace(/\%/g, ""); //für Systemnachrichten
            STUDIP.IM.createDialog(user_id, moremessages['stream_name']);
            var something_inserted = false;
            jQuery.each(moremessages['stream'], function (index, message) {
                if (STUDIP.IM.appendMessage(user_id, message.message_id, message.message, message.avatar)) {
                    something_inserted = true;
                }
            });
            if (something_inserted) {
                jQuery("#messenger_discussion_" + user_id).scrollTop(STUDIP.IM.grosseZahl);
            }
        });
    },
    resize: function () {
        jQuery("#messenger").css("height", jQuery(document).height());
        jQuery("#messenger_dialog").css(
            "height",
            jQuery(window).height() - jQuery("#messenger_contacts").height()
        );
        jQuery("#messenger_dialog .dialog_accordion").accordion("resize");
    },
    //Klick auf einen Avatar oben:
    openDialog: function () {
        var name = jQuery(this).find("img").attr("title");
        var id = jQuery(this).attr("id");
        id = id.substr(id.lastIndexOf("_") + 1);
        if (jQuery("#messenger_discussion_" + id).length === 0) {
            STUDIP.IM.createDialog(id, name);
        }
        jQuery("#messenger .dialog_accordion")
            .accordion("activate", "#messenger_discussion_head_" + id);
        STUDIP.IM.mark_the_read(id);
    },
    createDialog: function (id, name) {
        if (jQuery("#messenger_discussion_" + id).length === 0) {
            jQuery("#messenger .dialog_accordion")
                .append(jQuery("<h2/>").text(name).attr("id", "messenger_discussion_head_" + id))
            jQuery("#messenger .dialog_accordion")
                .append(jQuery(jQuery("#messenger_discussion_template > div").clone(true, true)).attr("id","messenger_discussion_" + id))
                .accordion('destroy');
            STUDIP.IM.makeAccordion();
        }
    },
    closeDialog: function () {
        var id = jQuery(this).closest('div[id^="messenger_discussion_"]').attr("id");
        id = id.substr(id.lastIndexOf("_") + 1);
        jQuery.ajax({
            url: STUDIP.ABSOLUTE_URI_STUDIP + "plugins.php/InstantMessenger/close_discussion",
            data: {
                'discussion_id': id
            },
            success: function () {
                jQuery("#messenger_discussion_" + id).remove();
                jQuery("#messenger_discussion_head_" + id).remove();
                STUDIP.IM.makeAccordion();
            }
        });
    },
    messengerIsOpen: function () {
        return parseInt(jQuery('#messenger').css('left')) >= 0;
    },
    makeAccordion: function (open) {
        var scroll_down = function (event, ui, changestart) {
            var user_id = jQuery(ui.newContent).attr("id");
            if (changestart) {
                jQuery(ui.newContent)
                    .hide()
                    .fadeIn(1000);
            }
            if (user_id) {
                user_id = user_id.substr(user_id.lastIndexOf("_")+1);
                jQuery("#messenger_discussion_" + user_id).scrollTop(STUDIP.IM.grosseZahl);
                STUDIP.IM.mark_the_read(user_id);
                jQuery("#messenger_discussion_head_" + user_id).removeClass("unread");
            }
        };
        var scroll_down_changestart = function (event, ui) {
            scroll_down(event, ui, true);
        };
        jQuery("#messenger .dialog_accordion")
            .accordion('destroy')
            .accordion({
                fillSpace: true,
                collapsible: true,
                active: open ? false : open,
                change: scroll_down,
                changestart: scroll_down_changestart
            });
        jQuery(window).trigger("resize");
    },
    opencloseMessenger: function (width) {
        width = parseInt(width,10);
        if (!STUDIP.IM.messengerIsOpen()) {
            jQuery('#messenger').animate({'left': '0px'});
            jQuery('#messenger_placeholder').animate({'min-width': (width + 10) + 'px'});
            jQuery("#barTopFont, #barTopMenu").animate({'margin-left': (width + 10) + "px"});
        } else {
            jQuery('#messenger').animate({'left': '-' + width + 'px'});
            jQuery('#messenger_placeholder').animate({'min-width': '10px'});
            jQuery("#barTopFont, #barTopMenu").animate({'margin-left': "10px"});
        }
        jQuery.ajax({
            url: STUDIP.ABSOLUTE_URI_STUDIP + "plugins.php/InstantMessenger/fold_messenger",
            data: {
                show: STUDIP.IM.messengerIsOpen() ? "0" : "1"
            }
        });
    },
    appendMessage: function (discussion_id, message_id, message, avatar) {
        if (jQuery("div#im_message_" + message_id).length === 0) {
            jQuery("#messenger_discussion_" + discussion_id + " div.messages").append(
                jQuery("<div/>")
                    .attr("id", "im_message_" + message_id)
                    .addClass("new")
                    .css('display', 'none')
                    //verschiedene Farben:
                    //.css("box-shadow", "inset 0px 0px 20px " + STUDIP.IM.intToRGBA(STUDIP.IM.hashCode(message.message_id)))
                    .html(message)
            );
            if (avatar) {
                jQuery("div#im_message_" + message_id).append(jQuery("<div style='clear:both'></div>"));
            }
            jQuery("div#im_message_" + message_id).prepend(jQuery("<div class='avatar'></div>").html(avatar ? avatar : ""));
            jQuery("div#im_message_" + message_id).fadeIn();
            return true;
        } else {
            return false;
        }
    },
    sendMessage: function (id) {
        discussion_id = id.substr(id.lastIndexOf("_")+1);
        var textarea = jQuery("#" + id + " textarea");
        var message = textarea.val();
        textarea.val('');
        jQuery.ajax({
            url: STUDIP.ABSOLUTE_URI_STUDIP + "plugins.php/InstantMessenger/send_message",
            data: {
                'discussion_id': discussion_id,
                'message': message
            },
            type: "POST",
            dataType: 'json',
            success: function (data) {
                STUDIP.IM.appendMessage(discussion_id, data.message_id, data.message, data.avatar);
                jQuery("#messenger_discussion_" + discussion_id).scrollTop(STUDIP.IM.grosseZahl);
            },
            error: function () {
                textarea.val(message);
            }
        });
        return false;
    },
    mark_the_read: function (discussion_id) {
        message_ids = [];
        jQuery("#messenger_discussion_" + discussion_id + " div.messages div.new").each(function (index, message) {
            var id = jQuery(message).attr("id");
            id = id.substr(id.lastIndexOf("_") + 1);
            message_ids.push(id);
        });
        jQuery.ajax({
            url: STUDIP.ABSOLUTE_URI_STUDIP + "plugins.php/InstantMessenger/mark_the_read",
            type: "POST",
            data: {
                'message_ids': message_ids,
                'discussion_id': discussion_id
            }
        });
    },
    helper: {
        string2number: function (text, modulo) {
            var number = 0;
            for (i=0; i < text.length; i++) {
                number = ((Math.pow(26, i) ) * text.charCodeAt(i)) % modulo;
            }
            return number;
        }
    }
};

//enables the periodical updater
STUDIP.jsupdate_enable = true;

jQuery(function () {
    jQuery(window).bind("resize", STUDIP.IM.resize);
    STUDIP.IM.makeAccordion();
    jQuery("#messenger_contacts > a").live("click", STUDIP.IM.openDialog);
    STUDIP.IM.pageTitle = window.document.title;

    jQuery("body").prepend(jQuery('<div id="everything">').css("display", "table-row"));
    jQuery('<div id="messenger_placeholder">').css({"display":"table-cell",'min-width':jQuery("#barTopFont").css("margin-left")}).appendTo("#everything");
    jQuery("#layout_wrapper").css({"display": "table-cell", 'padding-left': "0px", 'width': "100%"}).appendTo("#everything");
});
jQuery("messenger_dialog .dialog_accordion div.writer a.submit").live("click dblclick", STUDIP.IM.sendMessage);
jQuery("#messenger_dialog a.close").live("click dblclick", STUDIP.IM.closeDialog);
jQuery("#messenger_dialog .writer textarea").live("keydown", function (event) { 
    if (event.keyCode === 13 && !event.altKey && !event.ctrlKey && !event.shiftKey) {
        STUDIP.IM.sendMessage(jQuery(this).closest(".writer").parent().attr("id")); 
        event.preventDefault();
    }
});