<?php

/**
 * Обработка хуков, прилетающих от сервиса
 * Алгоритм
 * поступил звонок: прилетает хук event RINGING
 * поднял трубку: прилетает хук event ACCEPTED
 * звонок завершился: прилетает хук history
 *
 * Структура хука event
 * cmd          string Что за хук к нам прилетел event
 * callid       string Уникальный идентификатор вызова.
 * call-type    string Тип звока IN|OUT
 * call-status  string Статус звонка RINGING|ACCEPTED
 * exten        string Внутренний номер
 * phone        string Номер клиента
 * token        string Токен CRM из настроек плагина
 *
 * Структура хука history
 * cmd          string Что за хук к нам прилетел history
 * calldate     string Дата и время звонка
 * callid       string Уникальный идентификатор вызова.
 * call-type    string Тип звока IN|OUT
 * call-status  string Статус звонка ANSWERED|NO ANSWER|BUSY
 * exten        string Внутренний номер
 * phone        string Номер клиента
 * link         string Ссылка на файл записи разговора
 * duration     int    Длительность звонка в секундах
 * missed       string Флаг, что звонок пропущен 'true'|'false'
 * pickedup     string Флаг, что звонок перехвачен,
 * transfered   ыекштп Флаг, что звонок был перенаправлен,
 * token        string Токен CRM из настроек плагина
 */
class crmIptelefonsuPluginFrontendCallbackController extends waController
{
    public $plugin_id = "iptelefonsu";
    public $crm_key;

    public $cb_data;

    public function execute()
    {
        try {
            $this->crm_key = wa()->getSetting('crm_key', '', array('crm', $this->plugin_id));
        } catch (waDbException $e) {
            echo json_encode(array('error' => 'Something went wrong ...'));
        } catch (waException $e) {
            echo json_encode(array('error' => 'Something went wrong ...'));
        }

        $data = file_get_contents('php://input');
        $this->cb_data = json_decode($data, true);

        if (waSystemConfig::isDebug()) {
            $this->dumpLog($this->cb_data);
        }

        if ($this->crm_key !== ifset($this->cb_data['token'])) {
            $this->dumpLog('Received a callback with an invalid crm_token');
            header("HTTP/1.0 401 Invalid token");
            echo json_encode(array('error' => 'Invalid token'));
            return;
        }

        $cmd = ifempty($this->cb_data, 'cmd', '');

        if($cmd === 'event') {
            switch ($this->cb_data['call-status']) {
                case 'RINGING':
                    // новый звонок
                    $this->handleNewCall();
                    break;

                case 'ACCEPTED':
                    // ответили
                    $this->handleAnswer();
                    break;

                default:
                    echo json_encode(array('error' => 'Empty or unknown call-status'));
                    return;
            }
        } elseif ($cmd === 'history') {
            $this->handleHangup();
        } else {
            echo json_encode(array('error' => 'Unknown cmd'));
            return;
        }
    }

    protected function handleNewCall($history = false)
    {
        if ($this->cb_data['call-type'] === 'OUT') {
            $call_data = array(
                'direction' => 'OUT',
            );
        } else {
            $call_data = array(
                'direction'      => 'IN',
                'plugin_gateway' => $this->cb_data['phone'],
            );
        }

        $call_data += array(
            'plugin_user_number'   => $this->cb_data['exten'],
            'plugin_client_number' => $this->cb_data['phone'],
            'plugin_id'            => $this->plugin_id,
            'plugin_call_id'       => $this->cb_data['callid'],
            'create_datetime'      => ifempty($this->cb_data['calldate'], date('Y-m-d H:i:s')),
            'status_id'            => 'PENDING',
        );

        // Make sure call id is unique
        $existing_call = self::getCallModel()->getByField(array(
            'plugin_id'          => $this->plugin_id,
            'plugin_call_id'     => $call_data['plugin_call_id'],
            'plugin_user_number' => $call_data['plugin_user_number'],
        ));
        if ($existing_call) {
            // Additional dial-ins sometimes come for existing calls when call
            // is routed to the same extension repeatedly.
            $id = $existing_call['id'];
            self::getCallModel()->updateById($id, array(
                'status_id'         => 'PENDING',
                'finish_datetime'   => null,
                'plugin_record_id'  => null,
                'notification_sent' => 0,
                'duration'          => null,
            ));
            if (waSystemConfig::isDebug()) {
                $this->dumpLog('Existing record updated, id='.$id);

                $responseData = [
                    'received' => $this->cb_data,
                    'status' => 'Existing record updated, id=' . $id,
                ];

                echo json_encode($responseData);
            }
        } else {
            // Insert new call to db
            $id = self::getCallModel()->insert($call_data);
            if (waSystemConfig::isDebug()) {
                $this->dumpLog('New record created, id='.$id);

                $responseData = [
                    'received' => $this->cb_data,
                    'status' => 'New record created, id=' . $id,
                ];

                echo json_encode($responseData);
            }
        }

        if(!$history) {
            self::getCallModel()->handleCalls(array($id));
        }

        if (waSystemConfig::isDebug()) {
            $this->dumpLog('handleCalls() done');
        }
    }

    protected function handleAnswer()
    {
        $call = self::getCallModel()->getByField(array('plugin_id' => $this->plugin_id, 'plugin_call_id' => $this->cb_data['callid']));
        if (!$call) {
            if (waSystemConfig::isDebug()) {
                $this->dumpLog('Received HANGUP frontend callback for unknown or deleted call');
                $responseData = [
                    'received' => $this->cb_data,
                    'status' => 'Received HANGUP frontend callback for unknown or deleted call',
                ];

                echo json_encode($responseData);
            }
            return;
        }

        self::getCallModel()->updateById($call['id'], array(
            'status_id' => 'CONNECTED',
        ));
        $this->deletePendingDuplicates($call);
        self::getCallModel()->handleCalls(array($call['id']));
    }

    protected function handleHangup()
    {
        $call = self::getCallModel()->getByField(array('plugin_id' => $this->plugin_id, 'plugin_call_id' => $this->cb_data['callid']));

        if(!$call) {
            $this->handleNewCall(true);
        }

        $call = self::getCallModel()->getByField(array('plugin_id' => $this->plugin_id, 'plugin_call_id' => $this->cb_data['callid']));

        if (!$call) {
            if (waSystemConfig::isDebug()) {
                $this->dumpLog('Received HANGUP frontend callback for unknown or deleted call');
                $responseData = [
                    'received' => $this->cb_data,
                    'status' => 'Received HANGUP frontend callback for unknown or deleted call',
                ];

                echo json_encode($responseData);
            }
            return;
        }

        $call_data['finish_datetime'] = date('Y-m-d H:i:s');
        $call_data['duration'] = null;

        if($this->cb_data['transfered']) {
            $call_data['status_id'] = 'REDIRECTED';
        } else {
            switch ($this->cb_data['status']) {
                case 'ANSWERED':
                    $call_data['status_id'] = 'FINISHED';
                    $call_data['duration'] = (int)$this->cb_data['duration'];
                    $this->handleRecord();
                    break;

                default:
                    $call_data['status_id'] = 'DROPPED';
                    break;
            }
        }

        self::getCallModel()->updateById($call['id'], $call_data);
        self::getCallModel()->handleCalls(array($call['id']));
    }

    protected function handleRecord()
    {
        $call = self::getCallModel()->getByField(array('plugin_id' => $this->plugin_id, 'plugin_call_id' => $this->cb_data['callid']));
        if (!$call) {
            if (waSystemConfig::isDebug()) {
                $this->dumpLog('Received HANGUP frontend callback for unknown or deleted call');
            }
            return;
        }

        $call_data = array(
            'plugin_record_id' => $this->cb_data['link'],
        );

        if (waSystemConfig::isDebug()) {
            $responseData = [
                'received' => $this->cb_data,
                'status' => 'Record link saved',
            ];

            echo json_encode($responseData);
        }

        self::getCallModel()->updateById($call['id'], $call_data);
        self::getCallModel()->handleCalls(array($call['id']));
    }

    protected function deletePendingDuplicates($call)
    {
        self::getCallModel()->exec(
            "DELETE FROM crm_call
                 WHERE plugin_id = '{$this->plugin_id}'
                    AND plugin_call_id = ?
                    AND status_id IN ('PENDING', 'DROPPED')
                    AND id <> ?",
            $call['plugin_call_id'],
            $call['id']
        );
    }

    protected static function getCallModel()
    {
        static $call_model = null;
        if (!$call_model) {
            $call_model = new crmCallModel();
        }
        return $call_model;
    }

    protected function dumpLog($message)
    {
        waLog::dump($message, 'crm/plugins/'.$this->plugin_id.'.log');
    }
}
