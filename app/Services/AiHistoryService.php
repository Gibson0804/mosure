<?php

namespace App\Services;

use App\Models\AiAnswerHistory;

class AiHistoryService extends BaseService
{
    public function fingerprint(string $model, ?int $userId, string $question, string $messagesJson): string
    {
        $normQ = $this->normalize($question);
        $normM = $this->normalize($messagesJson);

        return hash('sha256', implode('|', [strtolower($model), (string) ($userId ?? 0), $normQ, $normM]));
    }

    public function findCached(string $model, ?int $userId, string $question, string $messagesJson): ?array
    {
        $fp = $this->fingerprint($model, $userId, $question, $messagesJson);
        $one = AiAnswerHistory::query()->where('fp', $fp)->first();
        $res = $one?->response_json ?? null;

        return json_decode($res, true);
    }

    public function save(string $model, ?int $userId, string $question, string $messagesJson, array $responseJson): AiAnswerHistory
    {
        $fp = $this->fingerprint($model, $userId, $question, $messagesJson);

        return AiAnswerHistory::query()->updateOrCreate(
            ['fp' => $fp],
            [
                'user_id' => $userId,
                'model' => $model,
                'question' => $question,
                'prompt' => $messagesJson,
                'response_json' => json_encode($responseJson, JSON_UNESCAPED_UNICODE),
            ]
        );
    }

    private function normalize(string $text): string
    {
        $text = trim($text);
        $text = preg_replace('/\s+/u', ' ', $text);

        return $text ?? '';
    }
}
