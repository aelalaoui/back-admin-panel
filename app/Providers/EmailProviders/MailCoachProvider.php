<?php

namespace App\Providers\EmailProviders;

use App\Services\EmailCoachService;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Validation\ValidationException;
use JetBrains\PhpStorm\ArrayShape;

class MailCoachProvider extends EmailProvider
{
    const STANDARD_TEMPLATE = 'standard_template';

    private EmailCoachService $service;

    private array $params;

    public function __construct(
        bool $withThrottle = false,
    ) {
        parent::__construct($withThrottle);

        $this->service = app(EmailCoachService::class);
    }

    /**
     * @return array
     * @throws Exception
     */
    protected function addAttachments(): array
    {
        $res = [];
        if (isset($this->params['attachments'])) {
            $res['attachments'] = $this->params['attachments'];
            unset($this->params['attachments']);
        }
        return $res;
    }

    /**
     * @param array $params
     * @return array
     * @throws Exception
     */
    #[ArrayShape(['replacements' => "array"])]
    protected function paramsToSend(array $params = []): array
    {
        return [
            'replacements' => array_merge(
                $params,
                $this->params,
            ),
        ];
    }

    /**
     * @return string|null
     * @throws Exception
     */
    protected function addButtonInTemplate(): ?string
    {
        $bgColor = "#FE8D6A";
        $textColor = "#FFFFFF";
        $label = $this->params['button_label'] ?? 'Clic to continue';

        if (isset($this->params['button_link'])) {
            return '<tr>
              <td width="auto" valign="middle" bgcolor="' . $bgColor . '" align="center" height="30"
              style="font-size:16px; font-family:Arial,Helvetica,sans-serif; color:#ffffff;
              font-weight:normal; padding-left:20px; padding-right:20px; vertical-align: middle;
              background-color:' . $bgColor . ';border-radius:4px;border-top:0px None #000;
              border-right:0px None #000;border-bottom:0px None #000;border-left:0px None #000;">
                <span style="text-decoration:none; color:#ffffff; font-weight:normal;">

                  <a href="' . $this->params['button_link'] . '"
                    style="text-decoration:none; color:' . $textColor . '; font-weight:normal;"
                  > ' . $label . ' </a>
                </span>
              </td>
            </tr>';
        }
        return null;
    }

    /**
     * @param string $text
     * @param array $data
     * @return string
     */
    protected function replacePlaceholders(string $text, array $data): string
    {
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $placeholder = '{' . $key . '}';
                $text = str_replace($placeholder, $value, $text);
            }
        }

        return $text;
    }

    /**
     * @throws GuzzleException
     * @throws ValidationException
     * @throws Exception
     */
    public function sendEmail(
        string $template,
        array $to,
        array $cc = [],
        array $bcc = [],
        array $params = [],
        string $subject = null,
        string $forceFrom = null
    ): array {
        $this->params = array_change_key_case($params, CASE_LOWER);

        $this->params['subject'] = $this->replacePlaceholders(
            $templateEmail->subject ?? $this->params['subject'] ?? 'YOU NEED TO ADD SUBJECT',
            $params
        );
        $this->params['title'] = $this->replacePlaceholders(
            $templateEmail->title ?? $this->params['title'] ?? 'YOU NEED TO ADD TITLE',
            $params
        );
        $this->params['body'] = $this->replacePlaceholders($this->params['body'], $params);
        $this->params['button'] = $this->addButtonInTemplate();
        $this->params['signature'] = isset($this->params['signature'])
            ? $this->replacePlaceholders($this->params['signature'], $params) : '';

        return $this->service->post(
            '',
            array_merge(
                [
                    "mail_name" => self::STANDARD_TEMPLATE,
                    "subject" => $this->params['subject'] ?? $subject,
                    "from" => $forceFrom,
                    "to" => implode(',', $to),
                    "cc" => implode(',', $cc),
                    "bcc" => implode(',', $bcc)
                ],
                $this->addAttachments(),
                $this->paramsToSend()
            )
        );
    }
}
