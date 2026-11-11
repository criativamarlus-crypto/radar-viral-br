<?php
declare(strict_types=1);

/**
 * Editorial viral-potential estimate (0–100) for short react videos.
 *
 * This is the Radar Viral BR's own heuristic. It does not measure audience, is not
 * an official ranking and never claims a story "went viral". Every point is explained.
 */

/** @return array<int,string> */
function radar_rank_stopwords(): array
{
    static $words = null;
    if ($words === null) {
        $words = array_fill_keys(explode(' ', 'de da do das dos a o as os e em no na nos nas num numa um uma uns umas para por pra com sem sob sobre entre ate apos que se ao aos como mais mas ou sua seu suas seus ele ela eles elas isso esse essa este esta estes estas ser sao foi era tem ter ha vai vao diz dizem disse apos antes depois quando onde qual quais novo nova novos novas ja so tambem ainda muito pouco veja saiba entenda confira fim volta apos de-para contra desde durante cada todo toda todos todas'), true);
    }
    return $words;
}

/** @return array<int,string> folded, de-duplicated, stopword-free tokens */
function radar_rank_tokens(string $text): array
{
    $stop = radar_rank_stopwords();
    $tokens = [];
    foreach (explode(' ', radar_fold($text)) as $token) {
        if ($token === '' || isset($stop[$token])) {
            continue;
        }
        if (strlen($token) < 3 && !ctype_digit($token)) {
            continue;
        }
        $tokens[$token] = true;
    }
    // Numeric words ("2027") become integer array keys: keep them strings.
    return array_map('strval', array_keys($tokens));
}

/** "G1 · Política" and "G1" are the same publisher for coverage counting. */
function radar_publisher_key(string $name): string
{
    $name = trim(explode('·', $name)[0]);
    $folded = radar_fold($name);
    foreach (['folha' => 'folha', 'g1' => 'g1', 'uol' => 'uol', 'globo' => 'globo', 'o globo' => 'globo', 'cnn' => 'cnn', 'metropoles' => 'metropoles', 'terra' => 'terra', 'estadao' => 'estadao', 'bbc' => 'bbc', 'ge' => 'ge', 'gazeta esportiva' => 'gazeta esportiva', 'veja' => 'veja', 'exame' => 'exame'] as $prefix => $canonical) {
        if ($folded === $prefix || str_starts_with($folded, $prefix . ' ')) {
            return $canonical;
        }
    }
    return $folded;
}

/**
 * Group headlines about the same story. Union-find over pairs that share enough
 * distinctive words; an inverted index keeps this near-linear for typical volumes.
 *
 * @param array<int,array{id:int,title:string}> $items
 * @return array<int,int> item id => cluster id (the smallest id in the cluster)
 */
function radar_rank_clusters(array $items): array
{
    $tokens = [];
    $index = [];
    foreach ($items as $position => $item) {
        $tokens[$position] = radar_rank_tokens($item['title']);
        foreach ($tokens[$position] as $token) {
            $index[$token][] = $position;
        }
    }
    $parent = array_keys($items);
    $find = static function (int $x) use (&$parent): int {
        while ($parent[$x] !== $x) {
            $parent[$x] = $parent[$parent[$x]];
            $x = $parent[$x];
        }
        return $x;
    };
    $checked = [];
    foreach ($index as $positions) {
        // Very common words create huge candidate lists and no signal.
        if (count($positions) < 2 || count($positions) > 60) {
            continue;
        }
        foreach ($positions as $i => $a) {
            foreach (array_slice($positions, $i + 1) as $b) {
                $pair = $a < $b ? $a . ':' . $b : $b . ':' . $a;
                if (isset($checked[$pair])) {
                    continue;
                }
                $checked[$pair] = true;
                $shared = count(array_intersect($tokens[$a], $tokens[$b]));
                $smaller = min(count($tokens[$a]), count($tokens[$b]));
                if ($smaller >= 3 && $shared >= 3 && $shared / $smaller >= 0.6) {
                    $rootA = $find($a);
                    $rootB = $find($b);
                    if ($rootA !== $rootB) {
                        $parent[max($rootA, $rootB)] = min($rootA, $rootB);
                    }
                }
            }
        }
    }
    $result = [];
    $smallest = [];
    foreach ($items as $position => $item) {
        $root = $find($position);
        $smallest[$root] = min($smallest[$root] ?? PHP_INT_MAX, $item['id']);
    }
    foreach ($items as $position => $item) {
        $result[$item['id']] = $smallest[$find($position)];
    }
    return $result;
}

/** Does a Google Trends query describe this headline? */
function radar_rank_trend_match(array $titleTokens, array $queryTokens): bool
{
    if ($queryTokens === []) {
        return false;
    }
    $present = count(array_intersect($queryTokens, $titleTokens));
    if (count($queryTokens) === 1) {
        return $present === 1 && strlen($queryTokens[0]) >= 5;
    }
    return $present === count($queryTokens) || ($present >= 2 && $present / count($queryTokens) >= 0.75);
}

/** @return array<string,int> category => base public-interest points (0–10) */
function radar_rank_category_interest(): array
{
    return ['Futebol' => 9, 'Esportes' => 6, 'Política' => 8, 'Famosos' => 8, 'Assuntos virais' => 8, 'Finanças pessoais' => 8, 'Cartão de crédito' => 7, 'Finanças' => 7, 'Economia' => 7, 'Notícias internacionais' => 6, 'Tecnologia' => 6, 'Entretenimento' => 6, 'Curiosidades' => 6, 'Geral' => 4];
}

/**
 * Score one headline.
 *
 * @param array<string,mixed> $item  title, summary, category, published_at, first_seen_at, image_url, source_count, trend_origin, source_weight
 * @param array<string,mixed> $context  now (int), trends (list of {query, rank, traffic, tokens})
 * @return array{score:int,reasons:array<int,array{label:string,points:int,max:int,text:string}>,trend_hits:array<int,array<string,mixed>>,summary:string}
 */
function radar_score_item(array $item, array $context): array
{
    $now = (int)($context['now'] ?? time());
    $title = (string)$item['title'];
    $titleFolded = ' ' . radar_fold($title) . ' ';
    $titleTokens = radar_rank_tokens($title);
    $reasons = [];
    $add = static function (string $label, float $points, int $max, string $text) use (&$reasons): void {
        $reasons[] = ['label' => $label, 'points' => (int)round(min($max, max(0, $points))), 'max' => $max, 'text' => $text];
    };

    // 1. Recency (25)
    $timestamp = isset($item['published_at']) && $item['published_at'] !== null ? (int)$item['published_at'] : null;
    if ($timestamp === null) {
        $age = max(0.0, ($now - (int)($item['first_seen_at'] ?? $now)) / 3600);
        $points = min(8.0, 25 * exp(-$age / 10));
        $add('Atualidade', $points, 25, 'Horário de publicação não informado pela fonte; considerei apenas quando o Radar viu a notícia.');
    } else {
        $age = max(0.0, ($now - $timestamp) / 3600);
        $points = 25 * exp(-$age / 10);
        $when = $age < 1 ? 'há menos de 1 hora' : ($age < 48 ? 'há ' . (int)round($age) . ' h' : 'há ' . (int)round($age / 24) . ' dias');
        $add('Atualidade', $points, 25, 'Publicada ' . $when . '.');
    }

    // 2. Coverage across publishers (20)
    $count = max(1, (int)($item['source_count'] ?? 1));
    $coverage = [1 => 0, 2 => 9, 3 => 14, 4 => 17][min($count, 5)] ?? 20;
    $add('Presença em fontes', $coverage, 20, $count === 1 ? 'Encontrada em um único veículo monitorado.' : 'O mesmo assunto aparece em ' . $count . ' veículos monitorados.');

    // 3. Trends (20)
    $hits = [];
    foreach ((array)($context['trends'] ?? []) as $trend) {
        if (radar_rank_trend_match($titleTokens, (array)($trend['tokens'] ?? []))) {
            $hits[] = ['query' => $trend['query'], 'rank' => (int)$trend['rank'], 'traffic' => $trend['traffic'] ?? null];
        }
    }
    usort($hits, static fn(array $a, array $b): int => $a['rank'] <=> $b['rank']);
    $hits = array_slice($hits, 0, 3);
    $trendPoints = 0;
    $trendText = 'Nenhuma busca em alta do Google Trends Brasil coincide com esta manchete.';
    if ($hits !== []) {
        $best = $hits[0];
        $trendPoints = 12 + ($best['rank'] <= 3 ? 8 : ($best['rank'] <= 10 ? 5 : 2));
        $trendText = 'Coincide com a busca em alta “' . $best['query'] . '” (posição ' . $best['rank'] . ' no Google Trends Brasil' . ($best['traffic'] ? ', tráfego aproximado ' . $best['traffic'] : '') . ').';
    } elseif (!empty($item['trend_origin'])) {
        $trendPoints = 10;
        $trendText = 'Listada pelo Google Trends como matéria associada à busca “' . $item['trend_origin'] . '”.';
    }
    $add('Tendência', $trendPoints, 20, $trendText);

    // 4. Public interest (10)
    $category = (string)($item['category'] ?? 'Geral');
    $interest = radar_rank_category_interest()[$category] ?? 4;
    $weight = max(0.5, min(1.5, (float)($item['source_weight'] ?? 1.0)));
    $add('Interesse público', min(10.0, $interest * min(1.0, 0.8 + 0.2 * $weight)), 10, 'Categoria “' . $category . '” (estimativa editorial de interesse geral).');

    // 5. Discussion potential (10)
    $discussion = 0;
    $signals = [];
    if (str_contains($title, '?')) {
        $discussion += 3;
        $signals[] = 'pergunta no título';
    }
    if (preg_match('/[“”"«»]/u', $title)) {
        $discussion += 2;
        $signals[] = 'declaração entre aspas';
    }
    $lexicon = ['polemica', 'critica', 'criticas', 'rebate', 'acusa', 'revela', 'denuncia', 'bomba', 'choca', 'surpreende', 'urgente', 'proibe', 'ameaca', 'demite', 'demitido', 'preso', 'presa', 'prisao', 'vaza', 'vazou', 'escandalo', 'briga', 'ataca', 'processa', 'versus', 'racha', 'crise', 'sai', 'volta', 'fim', 'morre', 'morreu', 'explode', 'viraliza', 'viralizou', 'reage', 'responde', 'debate', 'protesto', 'greve', 'decide', 'derrota', 'vitoria', 'recorde', 'historico', 'inedito'];
    $found = 0;
    foreach ($lexicon as $word) {
        if (str_contains($titleFolded, ' ' . $word . ' ')) {
            $found++;
        }
    }
    if ($found > 0) {
        $discussion += $found >= 2 ? 5 : 3;
        $signals[] = 'termo de conflito/novidade';
    }
    if (preg_match('/\b(?:primeira vez|maior|menor|pior|melhor)\b/iu', $title)) {
        $discussion += 2;
        $signals[] = 'superlativo';
    }
    $add('Potencial de discussão', $discussion, 10, $signals === [] ? 'Título descritivo, sem gatilhos claros de debate.' : 'Sinais no título: ' . implode(', ', $signals) . '.');

    // 6. Clarity (5)
    $length = mb_strlen($title);
    $clarity = ($length >= 35 && $length <= 110) ? 5 : ((($length >= 20 && $length < 35) || ($length > 110 && $length <= 140)) ? 3 : 1);
    $add('Clareza do assunto', $clarity, 5, 'Título com ' . $length . ' caracteres' . ($clarity === 5 ? ' (tamanho bom para entender em segundos).' : '.'));

    // 7. Image (5)
    $hasImage = !empty($item['image_url']);
    $add('Imagem disponível', $hasImage ? 5 : 0, 5, $hasImage ? 'Há imagem de capa declarada pela fonte.' : 'Sem imagem de capa até agora (dá para anexar ou capturar depois).');

    // 8. React viability (5)
    $friendly = in_array($category, ['Famosos', 'Futebol', 'Política', 'Curiosidades', 'Assuntos virais', 'Entretenimento'], true);
    $discussionPoints = $reasons[4]['points'];
    $react = ($friendly ? 2 : 0) + ($discussionPoints >= 4 ? 2 : 0) + ($hasImage ? 1 : 0);
    $add('Viabilidade de react', $react, 5, $friendly ? 'Tema que costuma render comentário pessoal em vídeo curto.' : 'Tema menos natural para react; exige mais contexto.');

    $total = 0;
    foreach ($reasons as $reason) {
        $total += $reason['points'];
    }
    $total = max(0, min(100, $total));

    $ranked = $reasons;
    usort($ranked, static fn(array $a, array $b): int => ($b['points'] / max(1, $b['max'])) <=> ($a['points'] / max(1, $a['max'])));
    $top = [];
    foreach ($ranked as $reason) {
        if ($reason['points'] / max(1, $reason['max']) >= 0.6 && count($top) < 3) {
            $top[] = mb_strtolower($reason['label']);
        }
    }
    $summary = $top === [] ? 'Pontuação baixa: poucos sinais de repercussão até agora.' : 'Pesaram a favor: ' . implode(', ', $top) . '.';

    return ['score' => $total, 'reasons' => $reasons, 'trend_hits' => $hits, 'summary' => $summary];
}
