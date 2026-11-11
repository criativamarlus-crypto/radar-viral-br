<?php
declare(strict_types=1);

/**
 * Dona Maricota do Babado: the TV character with three scenes per react, her registered profile and gallery,
 * the editorial memory and the cases A to H of the acceptance criteria. Included by run.php (same helpers).
 */
radar_setting_set('ai_provider', 'none');
$maricota = 'dona-maricota';
$tvNews = static function (int $id, string $title, string $summary, string $category, string $source = 'G1', ?int $cluster = null, ?string $url = null) use ($now): int {
    radar_db_exec(
        'INSERT INTO news (id, uid, source_name, title, url, summary, published_at, first_seen_at, last_seen_at, category, score, source_count, cluster_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 50, 1, ?)',
        [$id, 'tv' . $id, $source, $title, $url ?? 'https://' . strtolower($source) . '.example.com/materia-' . $id, $summary, $now - 3600, $now - 3600, $now - 3600, $category, $cluster]
    );
    return $id;
};
$tvImage = static function (string $role) use ($png, $maricota): array {
    return radar_media_save_bytes($png, 'reference', ['character' => $maricota, 'role' => $role, 'label' => 'teste ' . $role]);
};

check('Dona Maricota is registered: permanent id, 64-year-old TV host, TV style, three scenes, eight catchphrases and a studio', static function () use ($maricota): bool {
    $c = radar_character_get($maricota);
    return radar_character_valid($maricota) && $c['slug'] === 'dona-maricota' && radar_character_id($maricota) === 'dona-maricota' && $c['name'] === 'Dona Maricota do Babado' && $c['gender'] === 'f'
        && radar_character_format($maricota) === 'tv3' && radar_character_format('beto') === 'classic' && $c['version'] === 1 && $c['narration']['scene_seconds'] === 8 && $c['narration']['scenes'] === 3
        && count($c['catchphrases']) === 8 && in_array('Segura esse babado!', $c['catchphrases'], true) && in_array('Isso aqui vai dar pano pra manga!', $c['catchphrases'], true)
        && str_contains($c['identity_en'], '64 years old') && str_contains($c['identity_en'], 'platinum-blonde') && str_contains($c['identity_en'], 'cat-eye') && str_contains($c['identity_en'], 'leopard-print') && str_contains($c['identity_en'], 'MASTER')
        && str_contains($c['identity_pt'], '64 anos') && str_contains($c['identity_pt'], 'oncinha') && str_contains($c['voice'], 'levemente grave') && str_contains($c['scenery_en'], 'pink, purple and gold')
        && str_contains($c['scenery_en'], 'DONA MARICOTA DO BABADO') && count($c['negative_rules']) >= 8 && $c['fixed_look'] === true && !empty($c['gallery']);
});
check('the profile is saved with a version that goes up only when something really changes, and restores the default when emptied', static function () use ($maricota): bool {
    $v1 = radar_character_version($maricota);
    $same = radar_character_profile_save($maricota, ['voice' => radar_characters()[$maricota]['voice']]);
    $v2 = radar_character_profile_save($maricota, ['voice' => 'Voz de teste, mais rouca.', 'catchphrases' => "Minha filha...\nSegura esse babado!", 'scene_seconds' => 10]);
    $edited = radar_character_get($maricota);
    $v3 = radar_character_profile_save($maricota, ['voice' => '', 'catchphrases' => '', 'scene_seconds' => 8]);
    $restored = radar_character_get($maricota);
    $bad = rejects(static fn() => radar_character_profile_save($maricota, ['scene_seconds' => 40]), InvalidArgumentException::class) && rejects(static fn() => radar_character_profile_save($maricota, ['voice' => str_repeat('x', 901)]), InvalidArgumentException::class)
        && rejects(static fn() => radar_character_profile_save('inventado', ['voice' => 'x']), InvalidArgumentException::class);
    $name = radar_character_save($maricota, 'Dona Maricota do Babado', []);
    $afterName = radar_character_version($maricota);
    radar_character_save($maricota, 'Dona Maricota do Babado', ['Famosos']);
    radar_character_save($maricota, 'Dona Maricota do Babado', []);
    return $v1 === 1 && $same === 1 && $v2 === 2 && $edited['voice'] === 'Voz de teste, mais rouca.' && $edited['catchphrases'] === ['Minha filha...', 'Segura esse babado!'] && $edited['narration']['scene_seconds'] === 10
        && $v3 === 3 && $restored['voice'] === radar_characters()[$maricota]['voice'] && count($restored['catchphrases']) === 8 && $restored['narration']['scene_seconds'] === 8 && $bad
        && $afterName === 3 && radar_character_version($maricota) === 5;
});
check('a character can be made the default one, and the default takes the categories nobody covers', static function () use ($maricota): bool {
    $before = radar_character_default_slug();
    radar_character_default_set($maricota);
    $now = radar_character_default_slug() === $maricota && radar_character_slug(null) === $maricota && radar_character_for_category('Política') === $maricota && radar_character_for_category('Cartão de crédito') === 'clara';
    $invalid = rejects(static fn() => radar_character_default_set('inventado'), InvalidArgumentException::class);
    radar_setting_set('character_default', '');
    return $before === 'beto' && $now && $invalid && radar_character_default_slug() === 'beto' && radar_character_for_category('Política') === 'beto';
});
check('the gallery has the master image first and one image per slot: a new upload replaces the old one, and the order is fixed', static function () use ($maricota, $tvImage): bool {
    $surprise = $tvImage('surprise');
    $master = $tvImage('master');
    $frontal = $tvImage('frontal');
    $seated = $tvImage('seated');
    $versionBefore = radar_character_version($maricota);
    $master2 = $tvImage('master');
    $refs = radar_character_references($maricota);
    $order = array_column($refs, 'role');
    $replaced = radar_media_get($master['id']) === null && radar_character_version($maricota) === $versionBefore + 1;
    $extra1 = $tvImage('extra');
    $extra2 = $tvImage('extra');
    $roles = radar_character_reference_roles($maricota);
    $paragraph = radar_character_words(radar_reference_paragraph($roles), $maricota);
    $fullGallery = rejects(static function () use ($tvImage): void {
        foreach (range(1, 8) as $i) {
            $tvImage('extra');
        }
    }, InvalidArgumentException::class);
    foreach (radar_character_references($maricota) as $reference) {
        radar_media_delete((string)$reference['id']);
    }
    return $order === ['master', 'frontal', 'seated', 'surprise'] && $refs[0]['id'] === $master2['id'] && $replaced && count(radar_character_references($maricota)) === 0 && $fullGallery
        && count($roles) === 6 && $roles[0]['role'] === 'master' && str_contains($paragraph, 'the OFFICIAL MASTER image') && str_contains($paragraph, 'attach ALL 6 images') && str_contains($paragraph, 'FRONT view of her face')
        && str_contains($paragraph, 'SEATED at the presenter desk') && str_contains($paragraph, 'SURPRISED expression') && str_contains($paragraph, 'wins over every text description') && str_contains($paragraph, 'Do NOT copy from them the background')
        && $extra1['role'] === 'extra' && $extra2['role'] === 'extra';
});
check('the reference audio is optional, stays private, is validated and replaces the old one', static function () use ($maricota): bool {
    $wav = 'RIFF' . pack('V', 44) . 'WAVEfmt ' . pack('VvvVVvv', 16, 1, 1, 8000, 8000, 1, 8) . 'data' . pack('V', 8) . str_repeat("�", 8);
    $none = radar_character_voice_asset($maricota) === null;
    $versionBefore = radar_character_version($maricota);
    $first = radar_voice_save_bytes($wav, $maricota, 'Voz autorizada');
    $second = radar_voice_save_bytes($wav . 'x', $maricota);
    $current = radar_character_voice_asset($maricota);
    $bad = rejects(static fn() => radar_voice_save_bytes('isto nao e um audio', $maricota), InvalidArgumentException::class) && rejects(static fn() => radar_voice_save_bytes('', $maricota), InvalidArgumentException::class)
        && rejects(static fn() => radar_voice_save_bytes(str_repeat('a', RADAR_VOICE_MAX_BYTES + 1), $maricota), InvalidArgumentException::class);
    $ok = $none && $first['kind'] === 'voice' && str_ends_with($first['id'], '.wav') && radar_media_get($first['id']) === null && $current['id'] === $second['id'] && radar_media_path($second['id']) !== null && radar_media_path($first['id']) === null
        && radar_character_version($maricota) === $versionBefore + 2 && $bad && count(radar_character_references($maricota)) === 0 && radar_character_voice_asset('beto') === null;
    radar_media_delete($second['id']);
    return $ok && radar_character_voice_asset($maricota) === null;
});
check('paragraphs are scenes for the TV characters only (blank line between scenes), and long ones are still cut', static function (): bool {
    $text = "Primeira cena curta aqui.\n\nSegunda cena também curta.\n\nTerceira cena fecha o assunto.";
    $long = "Primeira.\n\n" . implode(' ', array_fill(0, 80, 'palavra')) . '.';
    return radar_scene_split($text, 3, true) === ['Primeira cena curta aqui.', 'Segunda cena também curta.', 'Terceira cena fecha o assunto.']
        && count(radar_scene_split($text, 3, false)) === 3 && radar_scene_split($text, 1, false) !== radar_scene_split($text, 3, true) || count(radar_scene_split($long, 2, true)) >= 3;
});

/* ---------- the three scenes ---------- */
$tvNews(701, 'Cantora famosa anuncia turnê com 20 shows pelo país', 'A artista confirmou a turnê em entrevista. Os ingressos começam a ser vendidos na semana que vem, segundo a assessoria.', 'Famosos', 'Quem');
$tvNews(702, 'Ator conhece fã que o esperava há 10 anos no aeroporto', 'O encontro foi gravado por passageiros e viralizou nas redes sociais nesta terça.', 'Famosos', 'Gshow');
$tvImage = static function (string $role) use ($png, $maricota): array {
    return radar_media_save_bytes($png, 'reference', ['character' => $maricota, 'role' => $role, 'label' => 'teste ' . $role]);
};
$galleryIds = [];
foreach (['master', 'frontal', 'fullbody', 'seated', 'surprise'] as $role) {
    $galleryIds[$role] = $tvImage($role)['id'];
}
check('a react of Dona Maricota is three connected scenes with everything each scene needs, saved in the database', static function () use ($maricota, $galleryIds): bool {
    $script = radar_react_generate(701, ['character' => $maricota]);
    $scenes = $script['scenes_data'];
    $rows = radar_tv_scene_rows($script['id']);
    $fields = ['number', 'role', 'objective', 'duration_seconds', 'narration', 'emotion', 'expression', 'gestures', 'camera', 'camera_movement', 'continuity', 'caption', 'screen_title', 'visual_reference', 'muse_prompt'];
    foreach ($scenes as $scene) {
        foreach ($fields as $field) {
            if (!array_key_exists($field, $scene) || $scene[$field] === '' || $scene[$field] === []) {
                fwrite(STDERR, "  scene field missing: $field\n");
                return false;
            }
        }
    }
    return $script['format'] === 'tv3' && $script['character'] === $maricota && $script['character_id'] === 'dona-maricota' && $script['character_version'] === radar_character_version($maricota) && count($scenes) === 3 && count($rows) === 3 && $script['scenes'] === 3
        && array_column($scenes, 'role') === ['gancho', 'desenvolvimento', 'reacao'] && array_column($scenes, 'camera') === ['CLOSE', 'PLANO_MEDIO', 'CLOSE'] && array_column($scenes, 'duration_seconds') === [8, 8, 8]
        && $script['layout'] === 'tv_studio' && $script['hook'] === $scenes[0]['narration'] && $script['script'] === $scenes[1]['narration'] && count($script['video_prompts']) === 3 && $script['video_title'] !== '' && $script['publication_caption'] !== ''
        && str_contains($script['spoken'], "\n\n") && count($script['reference_check'] ?? [1]) >= 1 && str_contains($script['scenes_data'][1]['narration'], 'Quem') && $script['strategy'] !== ''
        && count($scenes[0]['reference_ids']) === 5 && $scenes[0]['reference_ids'][0] === $galleryIds['master'] && $script['subtitles_srt'] !== '' && $script['duplicate_warning'] === false;
});
check('every scene prompt declares the same registered character, lists the references, the studio, the voice, the language, the exact narration and the rules', static function () use ($maricota): bool {
    $script = radar_react_generate(702, ['character' => $maricota]);
    foreach ($script['scenes_data'] as $index => $scene) {
        $p = $scene['muse_prompt'];
        foreach (['THIS IS THE SAME OFFICIAL CHARACTER REGISTERED IN THE PANEL: Dona Maricota do Babado', 'REFERENCE IMAGES (attach ALL 5 images', 'the OFFICIAL MASTER image', 'platinum-blonde', 'leopard-print', 'pink, purple and gold', 'DONA MARICOTA DO BABADO',
            'vertical 9:16', 'about 8 seconds', 'Brazilian Portuguese', 'slightly deep and full-bodied', 'DIALOGUE (spoken in natural, colloquial Brazilian Portuguese, with lip-sync, exactly as written):', '"' . $scene['narration'] . '"', 'do not add, remove, change or invent any word',
            'EMOTIONAL TONE', 'FACIAL EXPRESSION', 'BODY LANGUAGE', 'CAMERA FOR THIS SCENE', 'CONTINUITY', 'ENDING (default rule', 'AVOID', 'a different face', 'extra people', 'invented lines', 'Smooth body motion', 'SCREEN TEXT: none inside the video', 'SCENE ' . ($index + 1) . ' OF 3'] as $needle) {
            if (!str_contains($p, $needle)) {
                fwrite(STDERR, "  scene " . ($index + 1) . " prompt lacks: $needle\n");
                return false;
            }
        }
        if (str_contains($p, '{') || str_contains($p, 'selfie') || str_contains($p, 'Black Brazilian man')) {
            return false;
        }
    }
    $two = $script['scenes_data'][1]['muse_prompt'];
    return str_contains($two, 'does NOT greet again') && !str_contains($script['scenes_data'][0]['muse_prompt'], 'does NOT greet again') && $script['direction']['outfit'] === radar_characters()[$maricota]['default_outfit'];
});
check('case A: two different celebrity news keep the same face, voice, studio and wardrobe but use different openings', static function () use ($maricota): bool {
    $a = radar_react_generate(701, ['character' => $maricota, 'on_duplicate' => 'allow']);
    $b = radar_react_generate(702, ['character' => $maricota]);
    $first = $a['scenes_data'][0]['muse_prompt'];
    $second = $b['scenes_data'][0]['muse_prompt'];
    $identity = static fn(string $p): string => substr($p, (int)strpos($p, 'CHARACTER (use the attached'), 1200);
    return $a['scenes_data'][0]['narration'] !== $b['scenes_data'][0]['narration'] && $identity($first) === $identity($second) && $a['direction']['outfit'] === $b['direction']['outfit'] && $a['direction']['accessories'] === $b['direction']['accessories']
        && $a['scenes_data'][0]['reference_ids'] === $b['scenes_data'][0]['reference_ids'] && substr_count($first, 'slightly deep and full-bodied') === 1 && str_contains($second, 'slightly deep and full-bodied')
        && array_column($a['scenes_data'], 'narration') !== array_column($b['scenes_data'], 'narration');
});
check('case B: the same news from two sources is detected and nothing is generated by itself', static function () use ($maricota, $tvNews): bool {
    $tvNews(703, 'Cantora famosa anuncia turnê com 20 shows pelo país', 'Mesma informação em outro veículo, com texto parecido para o teste.', 'Famosos', 'Extra', 703);
    radar_db_exec('UPDATE news SET cluster_id = 703 WHERE id IN (701, 703)');
    $before = (int)radar_db_value('SELECT COUNT(*) FROM scripts');
    $caught = null;
    try {
        radar_react_generate(703, ['character' => $maricota, 'on_duplicate' => 'block']);
    } catch (RadarDuplicateException $error) {
        $caught = $error;
    }
    $sameNews = null;
    try {
        radar_react_generate(701, ['character' => $maricota, 'on_duplicate' => 'block']);
    } catch (RadarDuplicateException $error) {
        $sameNews = $error;
    }
    $none = (int)radar_db_value('SELECT COUNT(*) FROM scripts') === $before;
    $allowed = radar_react_generate(703, ['character' => $maricota, 'on_duplicate' => 'allow']);
    $classic = null;
    try {
        radar_react_generate(701, ['character' => 'beto', 'on_duplicate' => 'block']);
    } catch (RadarDuplicateException $error) {
        $classic = $error;
    }
    return $caught !== null && $caught->matches[0]['type'] === 'same_event' && $caught->matches[0]['news_id'] === 701 && str_contains($caught->getMessage(), 'Nada foi gerado') && $sameNews !== null && $sameNews->matches[0]['type'] === 'same_news'
        && $none && $allowed['format'] === 'tv3' && $classic !== null && radar_editorial_duplicates(radar_news_get(710) ?? ['id' => 0, 'title' => 'Assunto totalmente diferente sobre outra coisa', 'url' => 'https://x.example.com/1']) === [];
});
check('the same page with tracking parameters, and an equivalent headline from another outlet, are duplicates; unrelated news are not', static function () use ($tvNews): bool {
    $url = radar_editorial_canonical_url('https://www.G1.example.com/a/b/?utm_source=x&fbclid=1&id=7#topo');
    $other = $tvNews(704, 'Ator conhece fã que o esperava há 10 anos no aeroporto, e a cena viraliza', 'Resumo de outra fonte.', 'Famosos', 'Terra');
    $matches = radar_editorial_duplicates(radar_news_get(704));
    $unrelated = $tvNews(705, 'Prefeitura anuncia obras de drenagem no centro da cidade', 'Obras começam em março.', 'Geral', 'Folha');
    return $url === 'g1.example.com/a/b?id=7' && $matches !== [] && in_array($matches[0]['type'], ['similar_event', 'same_event'], true) && $matches[0]['news_id'] === 702 && radar_editorial_duplicates(radar_news_get(705)) === [];
});
check('case C: a real development of an old news is allowed as an update, using only the new facts; without a new fact it is refused', static function () use ($maricota, $tvNews): bool {
    $old = radar_react_generate(702, ['character' => $maricota]);
    radar_db_exec("UPDATE news SET summary = 'O ator disse que vai convidar o fã para a estreia do filme. O encontro tem 3 milhões de visualizações.' WHERE id = 702");
    $update = radar_react_generate(702, ['character' => $maricota, 'update_of' => $old['id'], 'context' => 'A produtora confirmou que o fã vai à estreia.']);
    radar_db_exec("UPDATE news SET summary = 'O encontro foi gravado por passageiros e viralizou nas redes sociais nesta terça.' WHERE id = 702");
    $noNews = rejects(static fn() => radar_react_generate(702, ['character' => $maricota, 'update_of' => $old['id']]), InvalidArgumentException::class);
    $missing = rejects(static fn() => radar_react_generate(702, ['character' => $maricota, 'update_of' => 999999]), InvalidArgumentException::class);
    $spoken = $update['spoken'];
    return $update['update_of'] === $old['id'] && str_starts_with($update['video_title'], 'ATUALIZAÇÃO') && $update['facts']['new'] !== [] && str_contains(implode(' ', $update['facts']['new']), 'estreia') && str_contains($spoken, 'Atualização:')
        && str_contains($update['scenes_data'][1]['narration'], 'estreia') && !str_contains($update['scenes_data'][1]['narration'], 'O encontro foi gravado por passageiros') && $noNews && $missing && str_contains(radar_script_export_text($update), 'ATUALIZAÇÃO do roteiro #' . $old['id']);
});
check('case D: finance news keep her personality, explain it popularly and never give financial advice', static function () use ($maricota, $tvNews): bool {
    $tvNews(711, 'Juros do cartão de crédito rotativo sobem para 450% ao ano, aponta o Banco Central', 'O Banco Central informou que a taxa média do rotativo subiu em setembro.', 'Cartão de crédito', 'Valor');
    $script = radar_react_generate(711, ['character' => $maricota]);
    $text = mb_strtolower($script['spoken']);
    return $script['facts']['tone'] === 'financas' && str_contains($text, 'não é recomendação financeira') && !preg_match('/\b(compre|venda|invista|aplique|garanta|lucro certo|dinheiro fácil)\b/u', $text) && str_contains($script['scenes_data'][1]['narration'], '450%')
        && $script['scenes_data'][0]['emotion'] === 'ATENCAO' && str_contains($script['scenes_data'][0]['muse_prompt'], 'attention: this one concerns everybody') && $script['character'] === $maricota;
});
check('case E: political news keep facts, context and attribution, and never invent accusations', static function () use ($maricota, $tvNews): bool {
    $tvNews(712, 'Senador é investigado por suspeita de desvio, diz Polícia Federal', 'Segundo a Polícia Federal, a investigação apura o caso. O senador nega as acusações e diz que vai colaborar.', 'Política', 'Folha');
    $script = radar_react_generate(712, ['character' => $maricota]);
    $text = mb_strtolower($script['spoken']);
    return $script['facts']['tone'] === 'politica' && preg_match('/(segundo|de acordo com) (o que )?folha/', $text) === 1 && str_contains($script['scenes_data'][1]['narration'], 'Polícia Federal') && !preg_match('/\b(culpado|corrupto|criminoso|ladrão|bandido)\b/u', $text)
        && $script['facts']['attributed'] !== [] && !str_contains($text, 'cometeu') && str_contains($script['scenes_data'][0]['muse_prompt'], 'questioning, attentive and fair attitude')
        && $script['scenes_data'][2]['expression'] === 'pensativo' && count(array_filter($script['facts']['verified'] + $script['facts']['attributed'])) >= 1;
});
check('case F: a sensitive news makes her drop jokes and catchphrases and speak with respect, in every scene', static function () use ($maricota, $tvNews): bool {
    $tvNews(713, 'Acidente em rodovia deixa três mortos e feridos graves', 'Segundo a concessionária, o acidente envolveu dois veículos. As vítimas foram levadas ao hospital.', 'Notícias gerais', 'G1');
    $script = radar_react_generate(713, ['character' => $maricota]);
    $text = $script['spoken'];
    $phrases = radar_character_get($maricota)['catchphrases'];
    $used = radar_editorial_catchphrases_used($text, $maricota);
    $tones = array_column($script['scenes_data'], 'emotion');
    return $script['facts']['tone'] === 'sensivel' && $used === [] && $tones === ['GRAVIDADE', 'INFORMACAO', 'EMPATIA'] && array_column($script['scenes_data'], 'expression') === ['serio', 'serio', 'serio'] && str_contains(mb_strtolower($text), 'respeito')
        && !preg_match('/kkk|haha|babado|pano pra manga/iu', $text) && str_contains($script['scenes_data'][0]['muse_prompt'], 'gravity and respect') && str_contains($script['scenes_data'][0]['muse_prompt'], 'serious and respectful expression')
        && $script['scenes_data'][1]['camera'] === 'PLANO_MEDIO' && $script['scenes_data'][2]['camera'] === 'PLANO_MEDIO' && str_contains($script['direction']['lighting'], 'softened') || str_contains($script['direction']['lighting'], 'calm');
});
check('case G: regenerating scene 2 changes only scene 2, keeps the other scenes and rebuilds the prompts', static function () use ($maricota): bool {
    $script = radar_react_generate(711, ['character' => $maricota]);
    $before = array_column($script['scenes_data'], 'narration');
    $beforePrompts = array_column($script['scenes_data'], 'muse_prompt');
    $new = radar_tv_scene_regenerate($script['id'], 2);
    $after = array_column($new['scenes_data'], 'narration');
    $afterPrompts = array_column($new['scenes_data'], 'muse_prompt');
    $bad = rejects(static fn() => radar_tv_scene_regenerate($script['id'], 4), InvalidArgumentException::class) && rejects(static fn() => radar_tv_scene_regenerate(999999, 1), InvalidArgumentException::class);
    $classic = radar_react_generate(603, ['character' => 'beto']);
    $notTv = rejects(static fn() => radar_tv_scene_regenerate($classic['id'], 1), InvalidArgumentException::class);
    return $after[0] === $before[0] && $after[2] === $before[2] && $after[1] !== $before[1] && $afterPrompts[0] === $beforePrompts[0] && $afterPrompts[2] === $beforePrompts[2] && $afterPrompts[1] !== $beforePrompts[1]
        && str_contains($afterPrompts[1], '"' . $after[1] . '"') && $new['hook'] === $after[0] && $new['script'] === $after[1] && $new['commentary'] === $after[2] && $new['id'] === $script['id'] && $bad && $notTv
        && count(radar_tv_scene_rows($script['id'])) === 3 && str_contains($new['subtitles_srt'], explode(' ', $after[1])[0]);
});
check('case H: the export for Muse has three complete prompts with the same reference, the exact narration, the titles and the subtitles', static function () use ($maricota): bool {
    $script = radar_react_generate(713, ['character' => $maricota, 'on_duplicate' => 'allow']);
    $text = radar_script_export_text($script);
    $references = [];
    foreach ($script['scenes_data'] as $scene) {
        $references[] = implode('|', $scene['reference_ids']);
        if (!str_contains($text, $scene['narration']) || !str_contains($text, $scene['muse_prompt']) || !str_contains($text, $scene['screen_title'])) {
            return false;
        }
    }
    $srt = radar_script_srt($script);
    return count(array_unique($references)) === 1 && substr_count($text, 'PROMPT PARA O MUSE:') === 3 && str_contains($text, 'TÍTULO DO VÍDEO') && str_contains($text, 'LEGENDA DE PUBLICAÇÃO') && str_contains($text, 'LEGENDAS (SRT')
        && str_contains($text, 'Dona Maricota do Babado (id dona-maricota, versão ' . $script['character_version'] . ')') && preg_match('/^1\n00:00:00,\d{3} --> 00:00:0\d,\d{3}\n/', $srt) === 1;
});
check('subtitles: numbered cues, valid times, within each scene and in order, short lines', static function (): bool {
    $srt = radar_tv_srt([['narration' => 'Meu povo, vocês viram isso? A cantora anunciou uma turnê enorme pelo país.', 'duration_seconds' => 8], ['narration' => 'Segundo a assessoria, os ingressos começam a ser vendidos na semana que vem.', 'duration_seconds' => 8]]);
    preg_match_all('/^(\d+)\n(\d\d):(\d\d):(\d\d),(\d{3}) --> (\d\d):(\d\d):(\d\d),(\d{3})\n(.+?)\n\n/ms', $srt, $m, PREG_SET_ORDER);
    $last = -1.0;
    foreach ($m as $i => $cue) {
        $start = $cue[2] * 3600 + $cue[3] * 60 + $cue[4] + $cue[5] / 1000;
        $end = $cue[6] * 3600 + $cue[7] * 60 + $cue[8] + $cue[9] / 1000;
        if ((int)$cue[1] !== $i + 1 || $start < $last || $end <= $start || $end > 16.01) {
            return false;
        }
        foreach (explode("\n", $cue[10]) as $line) {
            if (mb_strlen($line) > 38) {
                return false;
            }
        }
        $last = $end;
    }
    return count($m) >= 4 && radar_srt_time(3661.5) === '01:01:01,500' && radar_tv_srt([]) === '';
});

/* ---------- editorial memory and antirepetition ---------- */
check('every react is remembered: news, canonical URL, subject, entities, openings, closing, catchphrases, strategy and text', static function () use ($maricota): bool {
    $script = radar_react_generate(701, ['character' => $maricota]);
    $row = radar_db_one('SELECT * FROM editorial_history WHERE script_id = ?', [$script['id']]);
    $openings = json_decode((string)$row['openings'], true);
    $deleted = radar_script_delete($script['id']);
    return $row !== null && (int)$row['news_id'] === 701 && $row['character_slug'] === $maricota && (int)$row['character_version'] === $script['character_version'] && $row['canonical_url'] === 'g1.example.com/materia-701' && $row['strategy'] === $script['strategy']
        && count($openings) === 3 && $openings[0] === explode('.', $script['scenes_data'][0]['narration'])[0] . '.' || count($openings) === 3 && str_contains((string)$row['narration'], $script['scenes_data'][2]['narration']) && $row['closing'] !== '' && $deleted
        && radar_db_value('SELECT COUNT(*) FROM editorial_history WHERE script_id = ?', [$script['id']]) === 0 && radar_db_value('SELECT COUNT(*) FROM react_scenes WHERE script_id = ?', [$script['id']]) === 0;
});
check('the check finds a repeated opening, closing question and scene, and lets the catchphrases repeat', static function () use ($maricota): bool {
    radar_db_exec('DELETE FROM scripts WHERE character_slug = ?', [$maricota]);
    $first = radar_react_generate(701, ['character' => $maricota]);
    $first = radar_script_update($first['id'], ['scene_1' => 'Hoje saiu uma novidade quentinha no mundo dos famosos.', 'scene_3' => 'Na minha opinião, vale conferir a matéria. Você acompanharia esse assunto até o final? Comente aqui.']);
    $same = array_column($first['scenes_data'], 'narration');
    $issues = radar_editorial_check($same, $maricota);
    $kinds = array_column($issues, 'kind');
    $withBordao = radar_editorial_check(['Minha filha... Hoje saiu uma novidade quentinha no mundo dos famosos.', 'Outro desenvolvimento sem relação nenhuma com o primeiro.', 'Segura esse babado! Você acompanharia esse assunto até o final? Escreva aí.'], $maricota);
    $onlyPhrase = radar_editorial_check(['Meu povo, vocês viram isso?', 'Fato inédito sobre algo diferente.', 'Fecho inédito e outro. Pergunta nova sobre assunto novo?'], $maricota);
    $ignoringSelf = radar_editorial_check($same, $maricota, $first['id']);
    return in_array('abertura', $kinds, true) && in_array('fechamento', $kinds, true) && $issues[0]['with'] === $first['id'] && $ignoringSelf === [] && radar_editorial_check([], $maricota) === []
        && in_array('abertura', array_column($withBordao, 'kind'), true) && in_array('fechamento', array_column($withBordao, 'kind'), true) && $onlyPhrase === [];
});
check('with ten different news, no two reacts of the character open or close the same way', static function () use ($maricota, $tvNews): bool {
    radar_db_exec('DELETE FROM scripts WHERE character_slug = ?', [$maricota]);
    $titles = ['Cantor lança disco novo e esgota ingressos', 'Atriz vence prêmio internacional de cinema', 'Influenciadora mostra a nova casa em vídeo', 'Banda anuncia fim da turnê europeia', 'Apresentador estreia programa na televisão', 'Modelo desfila em semana de moda em Paris', 'Dupla sertaneja grava música com cantora famosa', 'Jogador famoso inaugura restaurante na capital', 'Escritora lança livro sobre a infância no interior', 'Humorista anuncia novo espetáculo de stand-up'];
    $openings = [];
    $closings = [];
    foreach ($titles as $i => $title) {
        $id = 720 + $i;
        $tvNews($id, $title, 'Resumo do veículo número ' . $i . ' com informação suficiente para o teste funcionar.', 'Famosos', 'Fonte' . $i);
        $script = radar_react_generate($id, ['character' => $maricota]);
        $openings[] = radar_fold($script['scenes_data'][0]['narration']);
        $closings[] = radar_fold(radar_editorial_closing($script['scenes_data'][2]['narration']));
    }
    $consecutive = false;
    foreach ($openings as $i => $opening) {
        $consecutive = $consecutive || ($i > 0 && $opening === $openings[$i - 1]);
    }
    // A catchphrase alone may open two videos that are not neighbors; nothing else repeats.
    return !$consecutive && count(array_unique($openings)) >= 8 && count(array_unique($closings)) >= 8 && count(array_unique(array_column(radar_db_all('SELECT strategy FROM editorial_history WHERE character_slug = ? ORDER BY id DESC LIMIT 6', [$maricota]), 'strategy'))) >= 4;
});
check('the strategy is not repeated in a row and sensitive or political news use the strategies that fit', static function () use ($maricota): bool {
    $last = (string)radar_db_value('SELECT strategy FROM editorial_history WHERE character_slug = ? ORDER BY id DESC LIMIT 1', [$maricota]);
    $picked = [];
    for ($i = 0; $i < 12; $i++) {
        $picked[] = radar_editorial_pick_strategy($maricota, array_keys(radar_editorial_strategies()), $i);
    }
    return !in_array($last, $picked, true) && radar_editorial_pick_strategy($maricota, ['explicacao'], 1) === 'explicacao' && radar_tv_strategies_for(radar_tv_tone(['title' => 'Morre cantor', 'summary' => '', 'category' => 'Famosos'])) === ['explicacao']
        && !in_array('ironia', radar_tv_strategies_for(radar_tv_tone(['title' => 'Senado vota', 'summary' => '', 'category' => 'Política'])), true);
});
check('facts are separated into reported, attributed and uncertain, and a headline alone is flagged as incomplete', static function (): bool {
    $facts = radar_tv_facts(['title' => 'Título qualquer da notícia', 'summary' => 'A empresa abriu 200 vagas na capital. Segundo o sindicato, os salários serão reajustados. O reajuste pode chegar a 5% no próximo ano.', 'source' => 'G1', 'published_at' => '2026-11-10T12:00:00+00:00'], 'O prazo termina em dezembro, segundo a empresa e confirmado ao site.');
    $only = radar_tv_facts(['title' => 'Só manchete', 'summary' => '', 'source' => 'G1', 'published_at' => null]);
    return str_contains(implode(' ', $facts['verified']), '200 vagas') && str_contains(implode(' ', $facts['attributed']), 'sindicato') && str_contains(implode(' ', $facts['uncertain']), '5%') && $facts['date'] === '10/11/2026' && $facts['incomplete'] === false
        && $only['incomplete'] === true && $only['verified'] === [] && radar_tv_sensitive('Homem morre em acidente') && !radar_tv_sensitive('Cantora lança disco novo') && radar_tv_tone(['title' => 'Prefeito anuncia obras', 'summary' => '', 'category' => 'Geral'])['key'] === 'politica';
});
check('the article page text is read from the paragraphs of the publisher, without navigation or scripts', static function (): bool {
    $html = '<html><head><script>var x=1;</script></head><body><nav><p>Menu do site com muitos links de navegação para ignorar aqui no texto final</p></nav><article><p>A prefeitura confirmou nesta segunda-feira que as obras de drenagem começam em março, em três bairros da cidade.</p><p>curto</p><p>Segundo o secretário, o investimento vem de verba do estado e a previsão de término é de oito meses de trabalho.</p></article><footer><p>Todos os direitos reservados ao portal de notícias da cidade inteira.</p></footer></body></html>';
    $text = radar_tv_article_text($html);
    return str_contains($text, 'obras de drenagem começam em março') && str_contains($text, 'oito meses') && !str_contains($text, 'Menu do site') && !str_contains($text, 'direitos reservados') && !str_contains($text, 'var x') && radar_tv_fetch_article('https://news.google.com/rss/articles/abc') === '';
});

/* ---------- with an AI ---------- */
$aiTv = static function (array $overrides = []): string {
    return json_encode($overrides + [
        'video_title' => 'Dona Maricota comenta a turnê da cantora', 'publication_caption' => 'A cantora anunciou 20 shows. E aí, você vai? Fonte: Quem.',
        'headlines' => ['Cantora anuncia turnê com 20 shows', 'Turnê chega ao país inteiro', 'Vem show por aí'], 'strategy' => 'contraste',
        'scenes' => [
            ['narration' => 'Tem notícia que chega quietinha e vira conversa no país todo, minha filha!', 'caption' => 'Tem notícia que chega quietinha.', 'screen_title' => 'TURNÊ ANUNCIADA'],
            ['narration' => 'Segundo a Quem, a cantora confirmou 20 shows, e os ingressos começam a ser vendidos semana que vem.', 'caption' => 'Segundo a Quem, 20 shows.', 'screen_title' => 'SEGUNDO A QUEM'],
            ['narration' => 'Na minha opinião, vai ter fila. Você vai garantir o seu ingresso?', 'caption' => 'Vai ter fila?', 'screen_title' => 'E VOCÊ?'],
        ],
        'facts' => ['verified' => ['A cantora confirmou a turnê com 20 shows.'], 'attributed' => ['Segundo a assessoria, os ingressos começam a ser vendidos na semana que vem.'], 'opinions' => ['Vai ter fila.'], 'uncertain' => []],
        'fact_check_notes' => ['Conferir as datas dos shows na matéria.'],
    ], JSON_UNESCAPED_UNICODE);
};
check('with an AI, the model writes the three scenes from the facts, the request carries the memory, and the result is validated and stored', static function () use ($maricota, $aiTv, $anthropicReply): bool {
    radar_setting_set('ai_provider', 'anthropic');
    $seen = [];
    $GLOBALS['radar_test_transport'] = static function (array $r) use ($aiTv, $anthropicReply, &$seen): array {
        $seen[] = $r['one'];
        return $anthropicReply($aiTv());
    };
    radar_db_exec('DELETE FROM scripts WHERE character_slug = ?', [$maricota]);
    $script = radar_react_generate(701, ['character' => $maricota]);
    unset($GLOBALS['radar_test_transport']);
    radar_setting_set('ai_provider', 'none');
    $body = json_decode($seen[0]['body'], true);
    $user = $body['messages'][0]['content'];
    return count($seen) === 1 && $script['mode'] === 'ia' && $script['scenes_data'][0]['narration'] === 'Tem notícia que chega quietinha e vira conversa no país todo, minha filha!' && $script['video_title'] === 'Dona Maricota comenta a turnê da cantora' && $script['strategy'] === 'contraste'
        && $script['scenes_data'][1]['screen_title'] === 'SEGUNDO A QUEM' && str_contains($body['system'], 'TRÊS cenas conectadas') && str_contains($body['system'], 'Bordões autorizados') && str_contains($user, 'palavras_maximas_por_cena') && str_contains($user, 'evitar_repetir')
        && str_contains($user, 'Cantora famosa anuncia turnê') && !str_contains($seen[0]['body'], 'sk-ant-api') && $body['max_tokens'] >= 2600 && $script['facts']['verified'] !== [] && $script['warnings'] === [] && $script['scenes_data'][0]['duration_seconds'] === 8;
});
check('an AI answer that repeats an earlier opening gets only that scene rewritten, and numbers that are not in the news are flagged', static function () use ($maricota, $aiTv, $anthropicReply): bool {
    radar_setting_set('ai_provider', 'anthropic');
    $calls = [];
    $GLOBALS['radar_test_transport'] = static function (array $r) use ($aiTv, $anthropicReply, &$calls): array {
        $calls[] = json_decode($r['one']['body'], true)['messages'][0]['content'];
        if (count($calls) === 1) {
            return $anthropicReply($aiTv(['scenes' => [
                ['narration' => 'Tem notícia que chega quietinha e vira conversa no país todo, minha filha!', 'caption' => 'a', 'screen_title' => 'A'],
                ['narration' => 'Segundo a Quem, a cantora confirmou 99 shows pelo país todo.', 'caption' => 'b', 'screen_title' => 'B'],
                ['narration' => 'Opinião minha: vai ter muita fila. Quem vai querer ver de pertinho?', 'caption' => 'c', 'screen_title' => 'C'],
            ]]));
        }
        return $anthropicReply(json_encode(['narration' => 'Ai, que novidade boa chegou hoje, hein?', 'caption' => 'x', 'screen_title' => 'NOVIDADE'], JSON_UNESCAPED_UNICODE));
    };
    $script = radar_react_generate(702, ['character' => $maricota]);
    unset($GLOBALS['radar_test_transport']);
    radar_setting_set('ai_provider', 'none');
    return count($calls) === 2 && str_contains($calls[1], 'regenerar a cena 1 (GANCHO)') && str_contains($calls[1], 'problema_a_evitar') && $script['scenes_data'][0]['narration'] === 'Ai, que novidade boa chegou hoje, hein?' && $script['scenes_data'][0]['screen_title'] === 'NOVIDADE'
        && str_contains($script['scenes_data'][1]['narration'], '99 shows') && count(array_filter($script['warnings'], static fn(string $w): bool => str_contains($w, 'Números no texto') && str_contains($w, '99'))) === 1;
});
check('an incomplete or failing AI answer falls back to the safe draft, visibly', static function () use ($maricota, $anthropicReply): bool {
    radar_setting_set('ai_provider', 'anthropic');
    $GLOBALS['radar_test_transport'] = static fn(array $r): array => $anthropicReply('{"scenes":[{"narration":"só uma cena"}]}');
    $script = radar_react_generate(713, ['character' => $maricota, 'on_duplicate' => 'allow']);
    unset($GLOBALS['radar_test_transport']);
    radar_setting_set('ai_provider', 'none');
    return $script['mode'] === 'rascunho' && count($script['scenes_data']) === 3 && str_contains(implode(' ', $script['warnings']), 'A IA falhou') && str_contains(implode(' ', $script['warnings']), 'menos de três cenas');
});
check('regenerating one scene with an AI keeps the other scenes and sends the neighbors as context', static function () use ($maricota, $anthropicReply): bool {
    $script = radar_react_generate(702, ['character' => $maricota]);
    radar_setting_set('ai_provider', 'anthropic');
    $seen = [];
    $GLOBALS['radar_test_transport'] = static function (array $r) use ($anthropicReply, &$seen): array {
        $seen[] = json_decode($r['one']['body'], true)['messages'][0]['content'];
        return $anthropicReply(json_encode(['narration' => 'Agora vem o detalhe: o encontro foi gravado por passageiros e viralizou nesta terça.', 'caption' => 'd', 'screen_title' => 'VIRALIZOU'], JSON_UNESCAPED_UNICODE));
    };
    $new = radar_tv_scene_regenerate($script['id'], 2, 'mais direto');
    unset($GLOBALS['radar_test_transport']);
    radar_setting_set('ai_provider', 'none');
    return count($seen) === 1 && str_contains($seen[0], 'regenerar a cena 2 (DESENVOLVIMENTO)') && str_contains($seen[0], 'mais direto') && str_contains($seen[0], $script['scenes_data'][0]['narration']) && $new['scenes_data'][1]['narration'] === 'Agora vem o detalhe: o encontro foi gravado por passageiros e viralizou nesta terça.'
        && $new['scenes_data'][0]['narration'] === $script['scenes_data'][0]['narration'] && $new['scenes_data'][2]['narration'] === $script['scenes_data'][2]['narration'] && $new['scenes_data'][1]['screen_title'] === 'VIRALIZOU';
});

/* ---------- the panel edits and the Studio still work ---------- */
check('editing a TV script changes scene texts, titles and caption, and the prompts follow; the character cannot be switched to a classic one', static function () use ($maricota): bool {
    $script = radar_react_generate(713, ['character' => $maricota, 'on_duplicate' => 'allow']);
    $edited = radar_script_update($script['id'], ['scene_1' => 'Hoje a notícia é de respeito e cuidado, gente.', 'scene_3_title' => 'respeito sempre', 'video_title' => 'Título novo', 'publication_caption' => 'Legenda nova', 'headline' => 'Outra headline', 'layout' => 'caption_card']);
    $switch = rejects(static fn() => radar_script_update($script['id'], ['character' => 'beto']), InvalidArgumentException::class);
    $toTv = rejects(static fn() => radar_script_update(radar_react_generate(603, ['character' => 'beto'])['id'], ['character' => 'dona-maricota']), InvalidArgumentException::class);
    $legacy = radar_script_update($script['id'], ['commentary' => 'Comentário pelo campo antigo. Opinião minha.']);
    return $edited['scenes_data'][0]['narration'] === 'Hoje a notícia é de respeito e cuidado, gente.' && $edited['hook'] === $edited['scenes_data'][0]['narration'] && str_contains($edited['scenes_data'][0]['muse_prompt'], '"Hoje a notícia é de respeito e cuidado, gente."')
        && $edited['scenes_data'][2]['screen_title'] === 'RESPEITO SEMPRE' && $edited['video_title'] === 'Título novo' && $edited['publication_caption'] === 'Legenda nova' && $edited['headlines'][0] === 'Outra headline' && $edited['layout'] === 'caption_card'
        && str_contains($edited['scenes_data'][0]['muse_prompt'], 'TEMPLATE LAYOUT') && $switch && $toTv && $legacy['scenes_data'][2]['narration'] === 'Comentário pelo campo antigo. Opinião minha.' && $legacy['scenes_data'][0]['narration'] === $edited['scenes_data'][0]['narration']
        && rejects(static fn() => radar_tv_scene_update($script['id'], 1, ['narration' => '  ']), InvalidArgumentException::class) && rejects(static fn() => radar_tv_scene_update($script['id'], 9, ['narration' => 'x']), InvalidArgumentException::class);
});
check('a long narration is never cut or sped up: the scene gets more time and a warning', static function () use ($maricota): bool {
    $script = radar_react_generate(713, ['character' => $maricota, 'on_duplicate' => 'allow']);
    $long = implode(' ', array_fill(0, 38, 'palavra')) . '.';
    $edited = radar_script_update($script['id'], ['scene_2' => $long]);
    $scene = $edited['scenes_data'][1];
    return $scene['duration_seconds'] === 16 && $scene['narration'] === $long && str_contains($scene['muse_prompt'], 'about 16 seconds') && str_contains($scene['muse_prompt'], 'never speed up the voice and never cut the sentence') && $scene['words'] === 38;
});
check('the Studio prompt for a TV character is built from the text with one paragraph per scene, with the studio and no selfie style', static function () use ($maricota): bool {
    $prompts = radar_video_prompts(['character' => $maricota, 'spoken' => "Primeira fala da cena um.\n\nSegunda fala da cena dois.\n\nTerceira fala da cena três.", 'expression' => 'surpreso', 'scene' => 'estudio', 'headline' => 'H', 'source' => 'S', 'scenes' => 3, 'layout' => 'tv_studio']);
    $card = radar_video_prompts(['character' => $maricota, 'spoken' => 'Só uma fala curta.', 'expression' => 'surpreso', 'scene' => 'estudio', 'headline' => 'Manchete real', 'source' => 'G1', 'scenes' => 1, 'layout' => 'caption_card', 'caption' => 'FRASE DE IMPACTO']);
    return count($prompts) === 3 && str_contains($prompts[1], '"Segunda fala da cena dois."') && str_contains($prompts[1], 'SCENE 2 OF 3') && str_contains($prompts[0], 'OBJECTIVE OF THIS SCENE: Hook') && str_contains($prompts[2], 'Reaction and closing') && !str_contains($prompts[0], 'selfie')
        && count($card) === 1 && str_contains($card[0], 'FRASE DE IMPACTO') && str_contains($card[0], 'MANCHETE REAL') && str_contains($card[0], 'SCENE 1 OF 1');
});
check('the default look of Dona Maricota never changes the outfit, only light, camera and gestures; classic characters still change the wardrobe', static function () use ($maricota): bool {
    $a = radar_direction_generate(['category' => 'Famosos', 'character' => $maricota], 3);
    $b = radar_direction_generate(['category' => 'Política', 'expression' => 'serio', 'character' => $maricota], 3);
    $classic = radar_direction_generate(['category' => 'Famosos', 'character' => 'beto'], 3);
    $default = radar_direction_default($maricota);
    return $a['outfit'] === $b['outfit'] && $a['outfit'] === radar_characters()[$maricota]['default_outfit'] && $a['accessories'] === $b['accessories'] && count($a['angles']) === 3 && count($a['gestures']) === 3 && $a['lighting'] !== $b['lighting'] || $a['mood'] !== $b['mood']
        && $default['outfit'] === $a['outfit'] && $classic['outfit'] !== radar_characters()[$maricota]['default_outfit'] && str_contains($b['lighting'], 'soft') || str_contains($b['lighting'], 'calm');
});
check('whichever of the three templates is chosen, every Dona Maricota prompt carries the template image as the first frame, the single-image board and the references', static function () use ($maricota): bool {
    foreach (array_keys(radar_video_templates()) as $layout) {
        $prompts = radar_video_prompts(['character' => $maricota, 'spoken' => "Primeira fala da cena um.\n\nSegunda fala da cena dois.\n\nTerceira fala da cena três.", 'expression' => 'surpreso', 'scene' => 'estudio', 'headline' => 'Manchete real da notícia', 'source' => 'Folha', 'scenes' => 3, 'layout' => $layout, 'caption' => 'FRASE', 'source_line' => 'FOLHA • 11/11/2026']);
        if (count($prompts) !== 3) {
            return false;
        }
        foreach ($prompts as $prompt) {
            foreach (['TEMPLATE IMAGE (attach the template image to the video tool', 'use it as the FIRST FRAME', 'SINGLE-IMAGE OPTION', 'REFERENCE BOARD', 'REFERENCE IMAGE', 'THIS IS THE SAME OFFICIAL CHARACTER REGISTERED IN THE PANEL'] as $needle) {
                if (!str_contains($prompt, $needle)) {
                    fwrite(STDERR, "  template $layout lacks: $needle\n");
                    return false;
                }
            }
        }
    }
    $studio = radar_video_prompts(['character' => $maricota, 'spoken' => 'Uma fala só.', 'expression' => 'surpreso', 'scene' => 'estudio', 'headline' => 'H', 'source' => 'S', 'scenes' => 1, 'layout' => 'tv_studio'])[0];
    $card = radar_video_prompts(['character' => $maricota, 'spoken' => 'Uma fala só.', 'expression' => 'surpreso', 'scene' => 'estudio', 'headline' => 'Manchete real', 'source' => 'Folha', 'scenes' => 1, 'layout' => 'caption_card', 'caption' => 'FRASE DE IMPACTO', 'source_line' => 'FOLHA • 11/11/2026'])[0];
    return str_contains($studio, 'SCREEN TEXT: none inside the video') && str_contains($studio, 'the studio, the desk, the lit sign') && str_contains($card, 'FRASE DE IMPACTO') && str_contains($card, 'FOLHA • 11/11/2026') && str_contains($card, 'MANCHETE REAL')
        && str_contains($studio, 'its screens come from the TEMPLATE IMAGE') && str_contains($studio, 'The signs and screens are the ones in the TEMPLATE IMAGE');
});
