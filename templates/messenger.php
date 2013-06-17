<?php
/* 
 *  Copyright (c) 2011  Rasmus Fuhse <fuhse@data-quest.de>
 * 
 *  This program is free software; you can redistribute it and/or
 *  modify it under the terms of the GNU General Public License as
 *  published by the Free Software Foundation; either version 2 of
 *  the License, or (at your option) any later version.
 */

$width = 210;

$left = UserConfig::get($GLOBALS['user']->id)->getValue("messenger_show") ? "0" : "-".($width - 10);

?>
<style>
    #messenger {
        left: <?= $left ?>px;
        width: <?= $width ?>px;
        /*background-image: url('<?= $plugin->getPluginURL()."/assets/nature_by_pr09studio_png-1280x800.jpg" ?>');*/
    }
    #messenger_dialog {
        max-width: <?= $width - 10 ?>px;
    }
    #messenger_dialog .dialog_accordion > div {
        max-width: <?= $width - 10 ?>px;
    }
    #messenger_dialog .dialog_accordion > h2 {
        max-width: <?= $width - 10 ?>px;
    }
    #messenger_dialog a.close {
        background: transparent url('<?= Assets::image_path("icons/16/blue/decline.png") ?>') no-repeat;
    }
    #messenger_dialog a.close:hover {
        background: transparent url('<?= Assets::image_path("icons/16/red/decline.png") ?>') no-repeat;
    }
    /* evil layout hacks */
    #layout_wrapper {
        padding-left: <?= $left + $width ?>px;
    }
    #barTopFont, #barTopMenu {
        margin-left: <?= $left + $width ?>px;
    }
</style>
<div id="messenger">
    <div id="messenger_layout_row" style="width: 100%">
        <div style="width: <?= $width - 10 ?>px">
            <div id="messenger_contacts"><? 
                foreach ($data['buddies'] as $buddy) : 
                    ?><a class="buddy <?= $buddy['user_id'] ?>" id="messenger_buddy_<?= $buddy['user_id'] ?>"><?= $buddy['avatar'] ?></a><?
                endforeach 
            ?></div>
            <div id="nobody_online">
                <div>
                    <?= _("Kein Buddy ist online") ?>
                </div>
                <? if (!$buddys) : ?>
                <div style="font-size: 0.8em; color: #aaaaaa; margin: 5px;">
                    <strong><?= _("Kleiner Tipp am Rande:") ?></strong><br>
                    <?= _("Dies ist der Instant-Messenger von Stud.IP. Hiermit können Sie sehen, wer von Ihren Buddys gerade online ist und mit diesen chatten. Ein gravierendes Problem dabei ist natürlich, dass Sie noch keine Buddys in Stud.IP haben. In der Online-Liste unter Community (oben auf der Seite) können Sie Leute, die online sind per gelbem Pfeil zu Ihrem Buddy machen. Oder Sie suchen eine bestimmte Person und klicken auf deren Profilseite auf den Link unterhalb des Profilbildes.") ?>
                </div>
                <? endif ?>
            </div>
            <div id="messenger_dialog">
                <div class="dialog_accordion">
                    <? foreach ($data['messages'] as $discussion_id => $discussion) : ?>
                    <h2 id="messenger_discussion_head_<?= htmlReady($discussion_id) ?>"><?= htmlReady($discussion['stream_name']) ?></h2>
                    <?= $this->render_partial("discussion.php", array(
                            'id' => $discussion_id,
                            'messages' => $discussion['stream']
                    )) ?>
                    <? endforeach ?>
                </div>
            </div>
        </div>
        <div id="messenger_drag" onClick="STUDIP.IM.opencloseMessenger('<?= $width - 10 ?>');">
            <div style="padding-left: 1px; padding-top: 1px;"></div>
        </div>
    </div>
</div>
<div id="messenger_discussion_template" style="display:none">
    <?= $this->render_partial("discussion.php") ?>
</div>
<script>
STUDIP.IM.makeAccordion(<?= $_SESSION['IM_OPENED_DISCUSSION'] ? "'#messenger_discussion_head_".$_SESSION['IM_OPENED_DISCUSSION']."'" : "" ?>);
STUDIP.IM.resize();
<?= $_SESSION['IM_OPENED_DISCUSSION'] ? 'jQuery(function () { jQuery("#messenger_discussion_'.$_SESSION['IM_OPENED_DISCUSSION'].'").scrollTop(STUDIP.IM.grosseZahl); });' : "" ?>
</script>