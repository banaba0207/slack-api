<?php

namespace SlackUtil;

use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;

class SlackApi
{
    /** @var Client */
    private $_client;

    private $_token;

    public function __construct($token)
    {
        $this->_client = new Client([
            'base_uri' => 'https://slack.com/api/'
        ]);

        $this->_token = $token;
    }

    public function postMessage($channelId, $text, Attachments $attachments = null)
    {
        $formParams = [
            'token' => $this->_token,
            'channel' => $channelId,
            'text' => $text,
            'as_user' => true,
        ];

        if ($attachments) {
            $formParams["attachments"] = json_encode($attachments->getAttachments());
        }

        $res = $this->_client->request(
            'POST',
            'chat.postMessage',
            [
                'form_params' => $formParams,
            ]
        );

        return $res;
    }

    /**
     * @param $channelId
     * @return HistoryParser
     */
    public function getMessageList($channelId)
    {
        $res = $this->_client->request(
            'GET',
            'groups.history',
            [
                'query' => [
                    'token' => $this->_token,
                    'channel' => $channelId,
                    'count' => 2
                ]
            ]
        );

        return HistoryParser::parse($res);
    }
}

class Attachments
{
    private $_attachments = [];

    const COLOR_GOOD = 'good';

    public function addAttachment($title, $text, $color = null)
    {
        $this->_attachments[] = [
            'title' => $title,
            'text' => $text,
            'color' => $color ?? self::COLOR_GOOD,
        ];
        return $this;
    }

    public function getAttachments()
    {
        return $this->_attachments;
    }
}

class HistoryParser
{
    private $_messageList = [];

    public static function parse(ResponseInterface $historyResponse)
    {
        $parsedObj = json_decode($historyResponse->getBody()->getContents());
        if (empty($parsedObj->ok)) {
            throw new \RuntimeException("メッセージ取得に失敗しました");
        }

        return new self($parsedObj);
    }

    private function __construct($historyResponse)
    {
        $messageList = [];
        foreach ($historyResponse->messages as $message) {
            $messageList[] = new Message($message);
        }
        $this->_messageList = $messageList;
    }

    /**
     * @return Message[]
     */
    public function getMessageList()
    {
        return $this->_messageList;
    }
}

class Message
{
    public $user;
    public $text;
    public $ts;
    public $dateTime;

    public function __construct($message)
    {
        $this->user = $message->user;
        $this->text = $message->text;
        $this->ts = $message->ts;
        $this->dateTime = date("Y-m-d H:i:s", $message->ts);
    }

    public function toUser()
    {
        return sprintf("<@%s>", $this->user);
    }
}
