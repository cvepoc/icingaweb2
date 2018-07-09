<?php
/* Icinga Web 2 | (c) 2018 Icinga Development Team | GPLv2+ */

namespace Icinga\Web\Widget;

use Icinga\Application\Hook\ApplicationStateHook;
use Icinga\Forms\AcknowledgeApplicationStateMessageForm;
use Icinga\Web\ApplicationStateCookie;
use Icinga\Web\Helper\HtmlPurifier;

/**
 * Render application state messages
 */
class ApplicationStateMessages extends AbstractWidget
{
    protected function getMessages()
    {
        $cookie = new ApplicationStateCookie();

        $acked = array_flip($cookie->getAcknowledgedMessages());
        $messages = ApplicationStateHook::getAllMessages();

        $active = array_diff_key($messages, $acked);

        return $active;
    }


    protected function getPurifier()
    {
        return new HtmlPurifier(['HTML.Allowed' => 'b,a[href|target],i,*[class]']);
    }

    public function render()
    {
        $active = $this->getMessages();

        if (empty($active)) {
            // Force container update on XHR
            return '<div style="display: none;"></div>';
        }

        $purifier = $this->getPurifier();

        $html = '<div>';

        reset($active);

        $id = key($active);
        $spec = current($active);
        $message = array_pop($spec); // We don't use state and timestamp here


        $ackForm = new AcknowledgeApplicationStateMessageForm();
        $ackForm->populate(['id' => $id]);

        $html .= $purifier->purify($message) . $ackForm;

        $html .= '</div>';

        return $html;
    }
}
