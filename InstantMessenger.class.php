<?php
/*
 *  Copyright (c) 2011  Rasmus Fuhse <fuhse@data-quest.de>
 *
 *  This program is free software; you can redistribute it and/or
 *  modify it under the terms of the GNU General Public License as
 *  published by the Free Software Foundation; either version 2 of
 *  the License, or (at your option) any later version.
 */

require_once 'lib/classes/UpdateInformation.class.php';

class InstantMessenger extends StudIPPlugin implements SystemPlugin {
    
    protected $subject = "Instant Message";
    
    /**
     * constructor
     */
    function __construct() {
        global $perm;
        if (!$perm->have_perm("autor") || $perm->have_perm("admin")) {
            return;
        }
        $mobile_browser = '0';
 
        if (preg_match('/(up.browser|up.link|mmp|symbian|smartphone|midp|wap|phone|android)/i', strtolower($_SERVER['HTTP_USER_AGENT']))) {
            $mobile_browser++;
        }

        if ((strpos(strtolower($_SERVER['HTTP_ACCEPT']),'application/vnd.wap.xhtml+xml') > 0) or ((isset($_SERVER['HTTP_X_WAP_PROFILE']) or isset($_SERVER['HTTP_PROFILE'])))) {
            $mobile_browser++;
        }    

        $mobile_ua = strtolower(substr($_SERVER['HTTP_USER_AGENT'], 0, 4));
        $mobile_agents = array(
            'w3c ','acs-','alav','alca','amoi','audi','avan','benq','bird','blac',
            'blaz','brew','cell','cldc','cmd-','dang','doco','eric','hipt','inno',
            'ipaq','java','jigs','kddi','keji','leno','lg-c','lg-d','lg-g','lge-',
            'maui','maxo','midp','mits','mmef','mobi','mot-','moto','mwbp','nec-',
            'newt','noki','oper','palm','pana','pant','phil','play','port','prox',
            'qwap','sage','sams','sany','sch-','sec-','send','seri','sgh-','shar',
            'sie-','siem','smal','smar','sony','sph-','symb','t-mo','teli','tim-',
            'tosh','tsm-','upg1','upsi','vk-v','voda','wap-','wapa','wapi','wapp',
            'wapr','webc','winw','winw','xda ','xda-');

        if (in_array($mobile_ua,$mobile_agents)) {
            $mobile_browser++;
        }

        if (strpos(strtolower($_SERVER['ALL_HTTP']),'OperaMini') > 0) {
            $mobile_browser++;
        }

        if (strpos(strtolower($_SERVER['HTTP_USER_AGENT']),'windows') > 0) {
            $mobile_browser = 0;
        }
        
        parent::__construct();
        if ($mobile_browser == 0) {
            if (UpdateInformation::isCollecting()) {
                //Wenn es der AJAX-Request ist:
                $data = $this->getData();
                UpdateInformation::setInformation("IM.process_data", $data);
            } elseif (!Request::isAjax()) {
                if (!in_array($GLOBALS['i_page'], array("studipim.php", "show_evaluation.php", "termin_eingabe_dispatch.php"))) {
                    //Wenn eine normale Seite:
                    PageLayout::addHeadElement("script", array('src' => $this->getPluginURL()."/assets/im.js"), "");
                    PageLayout::addHeadElement("link", array('rel' => "stylesheet", 'href' => $this->getPluginURL()."/assets/im.css", 'media' => "screen, print"));
                    $data = $this->getData(time() - mktime(5,0,0)); //alles ab 5 Uhr morgends
                    if (empty($data['buddys'])) {
                        $db = DBManager::get();
                        $buddys = $db->query(
                            "SELECT COUNT(*) " .
                            "FROM contact " .
                            "WHERE contact.owner_id = ".$db->quote($GLOBALS['user']->id)." " .
                                "AND contact.buddy = '1' " .
                        "")->fetch(PDO::FETCH_COLUMN, 0);
                    } else {
                        $buddys = count($data['buddys']);
                    }
                    $template = $this->getTemplate("messenger.php", null);
                    $template->set_attribute("data", $data);
                    $template->set_attribute("buddys", $buddys);
                    $template->set_attribute("plugin", $this);
                    PageLayout::addBodyElements(
                        $template->render()
                    );
                }
            }
        }
    }
    
    protected function getData($time = 120) {
        $db = DBManager::get();
        $data = array();

        //Buddys abholen:
        $online_buddies = $db->query(
            "SELECT contact.user_id, UNIX_TIMESTAMP(user_data.changed) AS time " .
            "FROM contact " .
                "INNER JOIN user_data ON (user_data.sid = contact.user_id) " .
            "WHERE contact.owner_id = ".$db->quote($GLOBALS['user']->id)." " .
                "AND contact.buddy = '1' " .
                "AND UNIX_TIMESTAMP(user_data.changed) > UNIX_TIMESTAMP() - (5*60) " .
        "")->fetchAll(PDO::FETCH_ASSOC);
        $data['buddies'] = array();
        foreach ($online_buddies as $buddy) {
            if ($buddy['user_id'] !== $GLOBALS['user']->id) {
                $data['buddies'][] = array(
                    'user_id' => $buddy['user_id'],
                    'name' => get_fullname($buddy['user_id']),
                    'time' => time() - $buddy['time'],
                    'avatar' => Avatar::getAvatar($buddy['user_id'])->getImageTag(Avatar::SMALL, array('title' => get_fullname($buddy['user_id']))),
                    'avatar_img' => Avatar::getAvatar($buddy['user_id'])->getCustomAvatarUrl(Avatar::SMALL)
                );
            }
        }

        //Nachrichten abholen:
        $message_statement = $db->prepare(
            "SELECT message.message_id, message.chat_id, message.autor_id, message.subject, message.message, message.mkdate, message_user.user_id AS adressat_id, message_user.readed " .
            "FROM message " .
                "INNER JOIN message_user ON (message_user.message_id = message.message_id) " .
            "WHERE ( " . //Nachrichten an mich, die ich nicht nicht gelesen habe oder 
                         //in letzter Zeit abgeschickt wurden
                    "message_user.user_id = :user_id " .
                    "AND (message_user.readed = '0' OR message.mkdate >= ".(time() - (int) $time)." ) " .
                    "AND message_user.snd_rec = 'rec' " .
                ") " .
                "OR ( " . //Nachrichten von mir selbst aus dieser Zeit
                    "message.autor_id = :user_id " .
                    "AND message.mkdate >= ".(time() - (int) $time)." " .
                    "AND message_user.snd_rec = 'rec' " .
                ") " .
            "ORDER BY message.mkdate ASC " .
        "");
        $message_statement->execute(array('user_id' => $GLOBALS['user']->id));
        $messages = $message_statement->fetchAll(PDO::FETCH_ASSOC);
        $data['messages'] = array();
        foreach ($messages as $message) {
            $message['autor_id'] = str_replace("%", "", $message['autor_id']);
            $message['autor_id'] = str_replace("_", "", $message['autor_id']);
            $discussion_id = $message['chat_id'] 
                ? $message['chat_id'] 
                : ($message['autor_id'] === $GLOBALS['user']->id ? $message['adressat_id'] : $message['autor_id']);
            $data['messages'][$discussion_id]['stream_name'] = $discussion_id !== "system" ? get_fullname($discussion_id) : _("Systemnachricht");
            $data['messages'][$discussion_id]['stream'][] = array(
                'message' => formatReady($message['message']),
                'message_id' => $message['message_id'],
                'autor' => $message['autor_id'] !== "system" ? get_fullname($message['autor_id']) : _("Systemnachricht"),
                'autor_id' => $message['autor_id'],
                'avatar' => $message['autor_id'] !== "system" ? Avatar::getAvatar($message['autor_id'])->getImageTag(Avatar::SMALL, array('title' => get_fullname($message['autor_id']))): false,
                'subject' => $message['subject'],
                'new' => ($message['autor_id'] === $GLOBALS['user']->id || $message['readed']) ? 0 : 1,
                'readed' => $message['readed'],
                'mkdate' => $message['mkdate']
            );
        }
        
        if (is_array($_SESSION['IM_CLOSED_DISCUSSIONS'])) foreach ($_SESSION['IM_CLOSED_DISCUSSIONS'] as $discussion_id) {
            $important = false;
            if (is_array($data['messages'][$discussion_id])) foreach ($data['messages'][$discussion_id]['stream'] as $message) {
                if ($message['new']) {
                    $important = true;
                }
            }
            if (!$important) {
                unset($data['messages'][$discussion_id]);
            }
        }
        return $data;
    }

    public function fold_messenger_action() {
        $config = UserConfig::get($GLOBALS['user']->id);
        //$config->setValue();
        $config->store("messenger_show", Request::int("show"));
    }
    
    public function send_message_action() {
        if (!count($_POST)) {
            return;
        }
        if (is_array($_SESSION['IM_CLOSED_DISCUSSIONS'])) foreach ($_SESSION['IM_CLOSED_DISCUSSIONS'] as $key => $discussion) {
            if ($discussion === Request::get("discussion_id")) {
                unset($_SESSION['IM_CLOSED_DISCUSSIONS'][$key]);
            }
        }
        $db = DBManager::get();
        $sms= new messaging;
        $ischat = $db->query(
            "SELECT chat_id FROM message WHERE chat_id = ".$db->quote(Request::get("discussion_id"))." " .
        "")->fetch();
        $message = studip_utf8decode(Request::get("message"));
        if ($ischat) {
            //Nachricht an den ganzen Chat:
        } else {
            //Nachricht an Buddy:
            $delivery= new messaging();
            $delivery->insert_message ($message, get_username(Request::get("discussion_id")), FALSE, FALSE, FALSE, FALSE, FALSE, $this->subject);
            $message_id = $db->query(
                "SELECT message_id " .
                "FROM message " .
                "WHERE autor_id = ".$db->quote($GLOBALS['user']->id)." " .
                    "AND message = ".$db->quote($message)." " .
                    "AND mkdate >= ".$db->quote(time() - 1). " " .
                "ORDER BY mkdate DESC " .
            "")->fetch(PDO::FETCH_COLUMN, 0);
        }
        $data = array(
            'message_id' => $message_id,
            'message' => studip_utf8encode(formatReady($message)),
            'avatar' => Avatar::getAvatar($GLOBALS['user']->id)->getImageTag(Avatar::SMALL)
        );
        echo json_encode($data);
    }
    
    public function mark_the_read_action() {
        $db = DBManager::get();
        $_SESSION['IM_OPENED_DISCUSSION'] = Request::option("discussion_id");
        foreach (Request::getArray("message_ids") as $message_id) {
            $db->exec(
                "UPDATE message_user " .
                "SET readed = '1' " .
                "WHERE user_id = ".$db->quote($GLOBALS['user']->id)." " .
                    "AND message_id = ".$db->quote($message_id)." " .
            "");
        }
    }
    
    public function open_discussion_action() {
        $discussion_id = Request::option("discussion_id");
        if ($discussion_id) {
            foreach ($_SESSION['IM_CLOSED_DISCUSSIONS'] as $key => $discussion) {
                if ($discussion === $discussion_id) {
                    unset($_SESSION['IM_CLOSED_DISCUSSIONS'][$key]);
                }
            }
        }
    }
    
    public function close_discussion_action() {
        $discussion_id = Request::option("discussion_id");
        if (!isset($_SESSION['IM_CLOSED_DISCUSSIONS'])) {
            $_SESSION['IM_CLOSED_DISCUSSIONS'] = array();
        }
        $_SESSION['IM_CLOSED_DISCUSSIONS'][] = $discussion_id;
        array_unique($_SESSION['IM_CLOSED_DISCUSSIONS']);
    }
    

    protected function getTemplate($template_file_name, $layout = "without_infobox") {
        if (!$this->template_factory) {
            $this->template_factory = new Flexi_TemplateFactory(dirname(__file__)."/templates");
        }
        $template = $this->template_factory->open($template_file_name);
        if ($layout) {
            $template->set_layout($GLOBALS['template_factory']->open($layout === "without_infobox" ? 'layouts/base_without_infobox' : 'layouts/base'));
        }
        return $template;
    }
  
  
}
