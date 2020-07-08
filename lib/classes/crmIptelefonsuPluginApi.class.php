<?php

class crmIptelefonsuPluginApi
{
    const PLUGIN_ID = "iptelefonsu";
    public $o;

    public function __construct()
    {
        $this->o = array(
            'pbx_url' => wa()->getSetting('pbx_url', '', array('crm', self::PLUGIN_ID)),
            'pbx_key' => wa()->getSetting('pbx_key', '', array('crm', self::PLUGIN_ID)),
        );

        if (empty($this->o['pbx_url'])) {
            throw new waException('Empty pbx url');
        }
        if (empty($this->o['pbx_key'])) {
            throw new waException('Empty pbx key');
        }
    }

    /**
     * Проверка API
     * @return bool
     */
    public function checkApi()
    {
        $data = [
            'topic' => 'base',
            'method' => 'ping',
        ];

        $this->addHash($data);

        try {
            $result = $this->getNet()->query($this->o['pbx_url'], $data);
        } catch (Exception $e) {
            $result = $e->getMessage();
        }

        $text = ifset($result, 'text', '');

        if($text === 'pong') {
            return true;
        }

        return false;
    }

    /**
     * Получение списка пользователей
     * @return array
     */
    public function getPbxUsers()
    {
        $numbers = [];

        $data = [
            'topic' => 'base',
            'method' => 'get-points',
        ];

        $this->addHash($data);

        try {
            $result = $this->getNet()->query($this->o['pbx_url'], $data);
        } catch (Exception $e) {
            $result = $e->getMessage();
        }

        if(is_array($result)) {
            foreach ($result as $item) {
                $numbers[$item] = $item;
            }
        }

        return $numbers;
    }

    /**
     * Инициализируем исходящий звонок
     * @param $from
     * @param $to
     * @param $call
     * @return bool
     * @throws waDbException
     * @throws waException
     */
    public function initCall($from, $to, $call)
    {
        $cm = new crmCallModel();
        $data = [
            'topic' => 'base',
            'method' => 'call-me',
            'src' => $from,
            'dst' => $to,
        ];

        $this->addHash($data);

        try {
            $res =  $this->getNet()->query($this->o['pbx_url'], $data, waNet::METHOD_POST);
            $code = ifempty($res, 'code', 'error');

            if($code === 'success') {
                $callId = ifset($res, 'callid', '');

                if (!empty($callId)) {
                    $cm->updateById($call['id'], array('plugin_call_id' => $callId));
                } else {
                    $cm->updateById($call['id'], array('status_id' => 'DROPPED'));
                }
            } else {
                $cm->updateById($call['id'], array('status_id' => 'DROPPED'));
                if (waSystemConfig::isDebug()) {
                    echo json_encode($res);
                    waLog::dump($res, 'crm/plugins/iptelefonsu.log');
                }
                return false;
            }

        } catch (Exception $e) {
            $cm->updateById($call['id'], array('status_id' => 'DROPPED'));
            return false;
        }
    }

    private function addHash(array &$data = [])
    {
        $data['hash'] = md5(http_build_query($data) . $this->o['pbx_key']);
    }

    protected function getNet($opts = array())
    {
        $params = array();
        return new waNet($opts + array(
                'request_format' => 'raw',
                'format'         => 'json',
            ), $params);
    }
}
