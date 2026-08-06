<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_geniai;

defined('MOODLE_INTERNAL') || die;

/**
 * Class api
 *
 * @package   local_geniai
 * @copyright 2025 Eduardo Kraus https://eduardokraus.com/
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api {

    /**
     * History api function.
     *
     * @param int $courseid
     * @param string $action
     * @return array
     *
     * @throws \dml_exception
     */
    public static function history_api($courseid, $action) {
        global $DB, $USER;

        $geniai = $DB->get_record("geniai", ["course" => $courseid]);
        $cmid = 0;
        if ($geniai) {
            $cm = get_coursemodule_from_instance('geniai', $geniai->id);
            $cmid = $cm ? $cm->id : 0;
        }

        $scenariocode = $geniai->scenariocode ?? 'anna';
        $activesession = $DB->get_record('local_geniai_sessions', ['userid' => $USER->id, 'courseid' => $courseid, 'cmid' => $cmid], '*', IGNORE_MULTIPLE);
        if ($activesession) {
            $scenariocode = $activesession->scenariocode;
        }

        $engine = new \local_geniai\bot_engine($USER->id, $courseid, $cmid, $scenariocode);

        if ($action === "clear") {
            $engine->reset_session();
        }

        $messages = $engine->get_messages();
        $returnmessage = [];

        foreach ($messages as $message) {
            $content = $message->message_text;
            if (strpos($content, "<audio") === false) {
                // If text contains HTML tags (e.g. <h3>, <strong>, <ul>, <br>), pass through directly; otherwise parse markdown.
                if (preg_match('/<[a-z][\s\S]*>/i', $content)) {
                    // Raw HTML rendered directly
                } else if (class_exists('\\local_geniai\\local\\markdown\\parse_markdown')) {
                    $parsemarkdown = new \local_geniai\local\markdown\parse_markdown();
                    $content = $parsemarkdown->markdown_text($content);
                }
            }

            $returnmessage[] = [
                "role" => ($message->sender === 'user') ? 'user' : 'system',
                "content" => $content,
                "format" => "html",
            ];
        }

        return [
            "result" => "true",
            "content" => json_encode($returnmessage),
        ];
    }

    /**
     * Chat api function.
     *
     * @param string $message
     * @param int $courseid
     * @param null $audio
     * @param string $lang
     * @return array
     *
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function chat_api($message, $courseid, $audio = null, $lang = "en") {
        global $DB, $USER;

        $maxMessageLength = 2500;
        if (strlen($message) > $maxMessageLength) {
            return ["result" => false, "format" => "text", "content" => "Error... Message too long. Please limit to {$maxMessageLength} characters."];
        }

        $maxAudioSizeBytes = 1000 * 1024;
        $transcription = null;
        if ($audio) {
            $audio = str_replace("data:audio/mp3;base64,", "", $audio);
            $audiodata = base64_decode($audio);
            if (strlen($audiodata) > $maxAudioSizeBytes) {
                return [
                    "result" => false,
                    "format" => "text",
                    "content" => "Error... Audio file is too large. Please upload audio under 1 KB.",
                ];
            }
            $transcription = self::transcriptions($audio, $lang);
            $message = $transcription["text"];
        }

        $cleanedMessage = strip_tags(trim($message));

        $geniai = $DB->get_record("geniai", ["course" => $courseid]);
        $cmid = 0;
        if ($geniai) {
            $cm = get_coursemodule_from_instance('geniai', $geniai->id);
            $cmid = $cm ? $cm->id : 0;
        }

        // Intercept special persona change command
        if (preg_match('/^\$\$persona=([a-zA-Z0-9_\-]+)\$\$$/', $cleanedMessage, $matches)) {
            $selected = $matches[1];
            $engine = new \local_geniai\bot_engine($USER->id, $courseid, $cmid, $selected);
            $engine->reset_session();

            $session = $engine->get_session_record();
            $session->scenariocode = $selected;
            $session->current_state = 'START';
            $session->timemodified = time();
            $DB->update_record('local_geniai_sessions', $session);

            $startnode = $engine->get_scenario()->get_state('START');
            $prompt = $startnode['bot_prompt'] ?? '';
            if (class_exists('\\local_geniai\\local\\markdown\\parse_markdown')) {
                $parsemarkdown = new \local_geniai\local\markdown\parse_markdown();
                $content = $parsemarkdown->markdown_text($prompt);
            } else {
                $content = $prompt;
            }

            return [
                "result" => "true",
                "format" => "html",
                "content" => $content,
            ];
        }

        // Moderate inappropriate content
        $moderationPrompt = [
            ["role" => "system", "content" => "You're a moderation AI. Decide if the following message contains profanity, foul language, mild insults words like dumb, etc. , or inappropriate content. Reply with only 'yes' or 'no'."],
            ["role" => "user", "content" => $cleanedMessage],
        ];

        $check = self::chat_completions($moderationPrompt);
        $decision = strtolower(trim($check["choices"][0]["message"]["content"] ?? "no"));

        if ($decision === "yes") {
            $engine = new \local_geniai\bot_engine($USER->id, $courseid, $cmid, 'anna');
            $engine->reset_session();
            return [
                "result" => "true",
                "format" => "html",
                "content" => "<strong>Grade - 0 out of 10</strong><br>Your message contains inappropriate language. This session is terminated.",
            ];
        }

        // Load active scenario from DB or fallback
        $activescenariocode = 'anna';
        if ($geniai && !empty($geniai->scenariocode)) {
            $activescenariocode = $geniai->scenariocode;
        }
        $activesession = $DB->get_record('local_geniai_sessions', ['userid' => $USER->id, 'courseid' => $courseid, 'cmid' => $cmid], '*', IGNORE_MULTIPLE);
        if ($activesession) {
            $activescenariocode = $activesession->scenariocode;
        }

        // Instantiate core engine and run active turn logic
        $engine = new \local_geniai\bot_engine($USER->id, $courseid, $cmid, $activescenariocode);
        $botreply = $engine->process_user_turn($cleanedMessage);

        // If botreply already contains HTML tags (e.g. <h3>, <ul>, <br>), pass through directly; otherwise parse markdown.
        if (preg_match('/<[a-z][\s\S]*>/i', $botreply)) {
            $content = $botreply;
        } else if (class_exists('\\local_geniai\\local\\markdown\\parse_markdown')) {
            $parsemarkdown = new \local_geniai\local\markdown\parse_markdown();
            $content = $parsemarkdown->markdown_text($botreply);
        } else {
            $content = $botreply;
        }

        return [
            "result" => "true",
            "format" => "html",
            "content" => $content,
            "transcription" => $transcription ? $transcription["text"] : null,
        ];
    }

    public static function chat_completions($messages, $ignoremaxtoken = false) {
        global $DB, $USER;

        // Check if Moodle Core AI provider framework is enabled & available
        if (class_exists('\\core_ai\\manager')) {
            try {
                $manager = new \core_ai\manager();
                $enabledproviders = $manager->get_provider_records();
                if (!empty($enabledproviders)) {
                    $lastmsg = end($messages);
                    $prompttext = is_array($lastmsg) ? ($lastmsg['content'] ?? '') : '';
                    $action = new \core_ai\action\generate_text(
                        contextid: \context_system::instance()->id,
                        userid: $USER->id,
                        prompttext: $prompttext
                    );
                    $result = $manager->process_action($action);
                    if ($result && method_exists($result, 'get_response_data')) {
                        $data = $result->get_response_data();
                        $generatedtext = $data['generatedcontent'] ?? ($data['response'] ?? '');
                        if (!empty($generatedtext)) {
                            return [
                                "choices" => [
                                    [
                                        "message" => [
                                            "content" => $generatedtext,
                                            "role" => "assistant",
                                        ]
                                    ]
                                ]
                            ];
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Seamlessly fallback to direct API call if core_ai manager process fails
            }
        }

        $strategy = get_config("local_geniai", "engine_strategy");
        if ($strategy === 'external_llm') {
            $apikey = get_config("local_geniai", "api_bearer_token");
            $model = get_config("local_geniai", "model_identifier");
            $api_base_url = get_config("local_geniai", "api_base_url");
        } else {
            $apikey = get_config("local_geniai", "apikey");
            $model = get_config("local_geniai", "model");
            $api_base_url = "https://api.openai.com/v1";
        }

        $maxtokens = get_config("local_geniai", "max_tokens");
        $frequencypenalty = get_config("local_geniai", "frequency_penalty");
        $presencepenalty = get_config("local_geniai", "presence_penalty");

        switch (get_config("local_geniai", "case")) {
            case "creative":
                $temperature = .7;
                $topp = .8;
                break;
            case "balanced":
                $temperature = .5;
                $topp = .7;
                break;
            case "precise":
                $temperature = .0;
                $topp = 1.0;
                break;
            case "exploration":
                $temperature = .8;
                $topp = .9;
                break;
            case "formal":
                $temperature = .3;
                $topp = .6;
                break;
            case "informal":
                $temperature = .7;
                $topp = .8;
                break;
            case "chatbot":
                $temperature = .2;
                $topp = .8;
                break;
            default:
                $temperature = .5;
                $topp = .5;
        }

        $messagesok = [];
        foreach ($messages as $message) {
            $message["content"] = strip_tags($message["content"]);
            $messagesok[] = $message;
        }

        $post = (object)[
            "model" => $model,
            "messages" => $messagesok,
            "temperature" => $temperature,
            "top_p" => $topp,
        ];

        if (floatval($frequencypenalty) != 0.0) {
            $post->frequency_penalty = floatval($frequencypenalty);
        }
        if (floatval($presencepenalty) != 0.0) {
            $post->presence_penalty = floatval($presencepenalty);
        }

        if (!$ignoremaxtoken) {
            $post->max_tokens = intval($maxtokens);
        }

        $baseurl = rtrim($api_base_url, '/');
        $url = $baseurl . '/chat/completions';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post));
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Authorization: Bearer {$apikey}",
        ]);

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            return [
                "error" => [
                    "message" => "http error: " . curl_error($ch),
                ],
            ];
        }
        curl_close($ch);

        $gpt = json_decode($result, true);

        $usage = (object)[
            "send" => json_encode($post, JSON_PRETTY_PRINT),
            "receive" => $result,
            "model" => $model,
            "prompt_tokens" => intval($gpt["usage"]["prompt_tokens"] ?? 0),
            "completion_tokens" => intval($gpt["usage"]["completion_tokens"] ?? 0),
            "timecreated" => time(),
            "datecreated" => date("Y-m-d", time()),
        ];
        try {
            $DB->insert_record("local_geniai_usage", $usage);
        } catch (\dml_exception $e) {
            echo $e->getMessage();
        }

        return $gpt;
    }

    /**
     * Handles audio transcription using OpenAI's Whisper API
     */
    private static function transcriptions($audio, $lang) {
        global $CFG;

        $audio = str_replace("data:audio/mp3;base64,", "", $audio);
        $audiodata = base64_decode($audio);
        $filename = uniqid();
        $filepath = "{$CFG->dataroot}/temp/{$filename}.mp3";
        file_put_contents($filepath, $audiodata);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.openai.com/v1/audio/transcriptions");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            "file" => curl_file_create($filepath),
            "model" => "whisper-1",
            "response_format" => "verbose_json",
            "language" => $lang,
        ]);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: multipart/form-data",
            "Authorization: Bearer " . get_config("local_geniai", "apikey"),
        ]);

        $result = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($result);

        return [
            "text" => $result->text,
            "language" => $result->language,
            "filename" => $filename,
        ];
    }

    /**
     * Converts text to speech using OpenAI's TTS API
     */
    private static function speech($input) {
        global $CFG;

        $json = json_encode((object)[
            "model" => "tts-1",
            "input" => $input,
            "voice" => get_config("local_geniai", "voice"),
            "response_format" => "mp3",
        ]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.openai.com/v1/audio/speech");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);

        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Authorization: Bearer " . get_config("local_geniai", "apikey"),
        ]);

        $audiodata = curl_exec($ch);
        curl_close($ch);

        $filename = uniqid();
        $filepath = "{$CFG->dataroot}/temp/{$filename}.mp3";
        file_put_contents($filepath, $audiodata);

        return "{$CFG->wwwroot}/local/geniai/load-audio-temp.php?filename={$filename}";
    }
}
