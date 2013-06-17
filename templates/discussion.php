<?php
/* 
 *  Copyright (c) 2011  Rasmus Fuhse <fuhse@data-quest.de>
 * 
 *  This program is free software; you can redistribute it and/or
 *  modify it under the terms of the GNU General Public License as
 *  published by the Free Software Foundation; either version 2 of
 *  the License, or (at your option) any later version.
 */

$messages || $messages = array();

?>
<div id="messenger_discussion_<?= htmlReady($id) ?>">
    <div style="float: right; margin-right: 20px;">
        <a class="close"></a>
    </div>
    <div style="clear: both;"></div>
    <div class="messages">
        <? foreach ($messages as $message) : ?>
        <div id="im_message_<?= $message['message_id'] ?>" class="<?= $message['new'] ? "new " : "" ?>">
            <? if ($message['autor_id'] && $message['autor_id'] !== "____%system%____") : ?>
            <div class="avatar"><?= Avatar::getAvatar($message['autor_id'])->getImageTag(Avatar::SMALL) ?></div>
            <? endif ?>
            <?= $message['message'] ?>
            <div style="clear: both"></div>
        </div>
        <? endforeach ?>
    </div>
    <div style="text-align: center;" class="writer">
        <form>
            <textarea></textarea>
            <div style="height: 10px;"></div>
        </form>
    </div>
</div>