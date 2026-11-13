<?php
declare(strict_types=1);

/**
 * HTTP tests of the TV character (Dona Maricota): generation, duplicate warning, regenerating one scene, subtitles, the pages,
 * the gallery and the reference audio. Included by http.php inside its try block (same variables: $post, $multipart, $writeCsrf, $png, ...).
 */
$maricotaId = 'dona-maricota';
foreach (['master', 'frontal', 'seated'] as $role) {
    [$galleryBody, $galleryHeaders] = $multipart(['action' => 'upload', 'kind' => 'reference', 'character' => $maricotaId, 'role' => $role, 'label' => $role], $role . '.png', 'image/png', $png);
    localRequest('api.php', 'POST', $galleryBody, array_merge($galleryHeaders, ['X-CSRF-Token: ' . $writeCsrf]));
}
check('the gallery upload keeps one image per slot: a second master replaces the first', static function () use ($multipart, $png, $writeCsrf, $maricotaId): bool {
    $before = array_column(radar_character_references($maricotaId), 'role');
    [$b, $h] = $multipart(['action' => 'upload', 'kind' => 'reference', 'character' => $maricotaId, 'role' => 'master', 'label' => 'Mestra nova'], 'm.png', 'image/png', $png);
    $reply = localRequest('api.php', 'POST', $b, array_merge($h, ['X-CSRF-Token: ' . $writeCsrf]));
    $after = radar_character_references($maricotaId);
    return $before === ['master', 'frontal', 'seated'] && $reply['status'] === 201 && array_column($after, 'role') === ['master', 'frontal', 'seated'] && $after[0]['label'] === 'Mestra nova';
});
$tvNews = (int)radar_db_value("SELECT id FROM news WHERE url = 'https://http-fixture.example.com/gol'");
radar_db_exec("INSERT INTO news (id, uid, source_name, title, url, summary, published_at, first_seen_at, last_seen_at, category, score, source_count) VALUES (9001, 'tvh1', 'Quem', 'Apresentadora anuncia volta aos palcos depois de dois anos', 'https://quem.example.com/volta-aos-palcos', 'A apresentadora confirmou a volta em entrevista. A estreia será em março, segundo a assessoria.', ?, ?, ?, 'Famosos', 50, 1)", [time() - 600, time() - 600, time() - 600]);
$tvGenerated = $post(['action' => 'generate', 'news_id' => 9001, 'character' => $maricotaId]);
$tvData = json_decode($tvGenerated['body'], true);
$tvItem = $tvData['item'] ?? [];
check('the API makes the three scenes of Dona Maricota with titles, subtitles and a prompt for each scene', static fn(): bool => $tvGenerated['status'] === 201 && ($tvItem['format'] ?? '') === 'tv3' && count($tvItem['scenes_data'] ?? []) === 3 && ($tvItem['character_id'] ?? '') === 'dona-maricota'
    && str_contains($tvItem['scenes_data'][0]['muse_prompt'], 'REGISTERED IN THE PANEL') && ($tvItem['video_title'] ?? '') !== '' && str_contains($tvItem['subtitles_srt'] ?? '', '-->') && ($tvItem['duplicate_warning'] ?? null) === false);
$tvDuplicate = $post(['action' => 'generate', 'news_id' => 9001, 'character' => $maricotaId]);
$tvDuplicateData = json_decode($tvDuplicate['body'], true);
$tvCount = (int)radar_db_value('SELECT COUNT(*) FROM scripts WHERE news_id = 9001');
check('asking again for the same news answers 409 with the existing react and generates nothing', static fn(): bool => $tvDuplicate['status'] === 409 && ($tvDuplicateData['duplicate'] ?? false) === true && ($tvDuplicateData['duplicates'][0]['script_id'] ?? 0) === ($tvItem['id'] ?? -1) && str_contains($tvDuplicateData['error'] ?? '', 'Nada foi gerado') && $tvCount === 1);
$tvForce = $post(['action' => 'generate', 'news_id' => 9001, 'character' => $maricotaId, 'allow_duplicate' => true]);
$tvUpdate = $post(['action' => 'generate', 'news_id' => 9001, 'character' => $maricotaId, 'update_of' => $tvItem['id'] ?? 0]);
$tvUpdateData = json_decode($tvUpdate['body'], true);
$tvUpdateOk = $post(['action' => 'generate', 'news_id' => 9001, 'character' => $maricotaId, 'update_of' => $tvItem['id'] ?? 0, 'context' => 'A produção confirmou que o show de estreia terá transmissão ao vivo.']);
$tvUpdateOkData = json_decode($tvUpdateOk['body'], true);
check('generate anyway works on request, and an update needs a new fact (and is saved as an update)', static fn(): bool => $tvForce['status'] === 201 && $tvUpdate['status'] === 400 && str_contains($tvUpdateData['error'] ?? '', 'fato novo') && $tvUpdateOk['status'] === 201 && ($tvUpdateOkData['item']['update_of'] ?? 0) === ($tvItem['id'] ?? -1));
$dupGet = localRequest('api.php?action=duplicates&news_id=9001&character=' . $maricotaId);
check('the duplicates check is available before generating (GET)', static fn(): bool => $dupGet['status'] === 200 && count(json_decode($dupGet['body'], true)['duplicates'] ?? []) >= 1 && localRequest('api.php?action=duplicates&news_id=999999')['status'] === 404);
$regen = $post(['action' => 'scene_regenerate', 'id' => $tvItem['id'] ?? 0, 'scene' => 2]);
$regenData = json_decode($regen['body'], true);
check('regenerating scene 2 through the API changes only that scene', static fn(): bool => $regen['status'] === 200 && ($regenData['item']['scenes_data'][1]['narration'] ?? '') !== ($tvItem['scenes_data'][1]['narration'] ?? '')
    && ($regenData['item']['scenes_data'][0]['narration'] ?? null) === ($tvItem['scenes_data'][0]['narration'] ?? '') && ($regenData['item']['scenes_data'][2]['narration'] ?? null) === ($tvItem['scenes_data'][2]['narration'] ?? '')
    && $post(['action' => 'scene_regenerate', 'id' => $tvItem['id'] ?? 0, 'scene' => 7])['status'] === 400 && $post(['action' => 'scene_regenerate', 'id' => 999999, 'scene' => 1])['status'] === 400);
$srt = localRequest('api.php?action=script&id=' . ($tvItem['id'] ?? 0) . '&format=srt');
$classicSrt = localRequest('api.php?action=script&id=' . (int)radar_db_value("SELECT id FROM scripts WHERE react_format = 'classic' ORDER BY id LIMIT 1") . '&format=srt');
check('the subtitles download is an .srt file (UTF-8 with BOM) for scripts made of scenes, and 404 for the others', static fn(): bool => $srt['status'] === 200 && str_contains($srt['headers']['content-type'] ?? '', 'application/x-subrip') && str_starts_with($srt['body'], "\xEF\xBB\xBF1\n00:00:00,") && str_contains($srt['headers']['content-disposition'] ?? '', '.srt') && $classicSrt['status'] === 404);
$tvTxt = localRequest('api.php?action=script&id=' . ($tvItem['id'] ?? 0) . '&format=txt');
check('the text export carries the titles, the three scenes with the Muse prompts and the subtitles', static fn(): bool => $tvTxt['status'] === 200 && substr_count($tvTxt['body'], 'PROMPT PARA O MUSE:') === 3 && str_contains($tvTxt['body'], 'TÍTULO DO VÍDEO') && str_contains($tvTxt['body'], 'LEGENDAS (SRT'));
$tvPage = localRequest('roteiro.php?id=' . ($tvItem['id'] ?? 0));
check('the script page of a TV react shows the three scenes, the regenerate buttons, the references, the facts and the downloads', static fn(): bool => $tvPage['status'] === 200 && substr_count($tvPage['body'], 'Refazer só esta cena') === 3 && str_contains($tvPage['body'], 'Copiar prompt da cena 3') && str_contains($tvPage['body'], 'Baixar legendas (.srt)')
    && str_contains($tvPage['body'], 'Anexe estas imagens em cada cena') && str_contains($tvPage['body'], 'Fatos usados') && str_contains($tvPage['body'], 'name="scene_2"') && str_contains($tvPage['body'], 'versão ' . (int)($tvItem['character_version'] ?? 0)) && str_contains($tvPage['body'], 'data-copy="video-prompt"'));
$tvEdit = localRequest('roteiro.php', 'POST', formBody(['csrf' => $writeCsrf, 'id' => $tvItem['id'] ?? 0, 'scene_1' => 'Cena um editada pelo usuário, gente.', 'video_title' => 'Meu título', 'layout' => 'tv_studio']), FORM);
check('editing the scenes through the form saves them and rebuilds the prompt', static fn(): bool => $tvEdit['status'] === 303 && radar_script_get($tvItem['id'] ?? 0)['scenes_data'][0]['narration'] === 'Cena um editada pelo usuário, gente.' && radar_script_get($tvItem['id'])['video_title'] === 'Meu título');
$characterPage = localRequest('personagem.php?c=' . $maricotaId);
check('Meus Personagens shows the registration, the gallery slots, the version and the audio upload for Dona Maricota', static fn(): bool => $characterPage['status'] === 200 && str_contains($characterPage['body'], 'Cadastro completo') && str_contains($characterPage['body'], 'Imagem-mestre (obrigatória)') && str_contains($characterPage['body'], 'Perfil direito') && str_contains($characterPage['body'], 'Perfil esquerdo')
    && str_contains($characterPage['body'], 'Corpo inteiro') && str_contains($characterPage['body'], 'Sentada no estúdio') && str_contains($characterPage['body'], 'Expressão de surpresa') && str_contains($characterPage['body'], 'Áudio de referência da voz') && str_contains($characterPage['body'], 'dona-maricota')
    && str_contains($characterPage['body'], 'Segura esse babado!') && str_contains($characterPage['body'], 'name="profile[catchphrases]"') && str_contains($characterPage['body'], 'Substituir') && str_contains($characterPage['body'], 'Tornar a personagem padrão'));
$versionBefore = radar_character_version($maricotaId);
$profileSave = localRequest('personagem.php', 'POST', formBody(['csrf' => $writeCsrf, 'slug' => $maricotaId, 'form' => 'profile', 'profile' => ['voice' => 'Voz atualizada pelo painel, bem rouca.', 'scene_seconds' => '9']]), FORM);
$defaultSave = localRequest('personagem.php', 'POST', formBody(['csrf' => $writeCsrf, 'slug' => $maricotaId, 'form' => 'default']), FORM);
check('saving the registration raises the version and the default character can be chosen in the panel', static function () use ($profileSave, $defaultSave, $versionBefore, $maricotaId): bool {
    $ok = $profileSave['status'] === 303 && $defaultSave['status'] === 303 && radar_character_version($maricotaId) === $versionBefore + 1 && radar_character_get($maricotaId)['voice'] === 'Voz atualizada pelo painel, bem rouca.'
        && radar_character_get($maricotaId)['narration']['scene_seconds'] === 9 && radar_character_default_slug() === $maricotaId;
    radar_character_profile_save($maricotaId, ['voice' => '', 'scene_seconds' => 8]);
    radar_setting_set('character_default', '');
    return $ok;
});
$dialog = localRequest('noticias.php');
check('the generate dialog preselects the default character and tells that the TV character always makes three scenes', static function () use ($dialog, $maricotaId): bool {
    radar_character_default_set($maricotaId);
    $html = radar_react_dialog();
    radar_setting_set('character_default', '');
    return preg_match('/value="dona-maricota" data-format="tv3" data-seconds="8" data-photo="[^"]*" selected/', $html) === 1 && str_contains($html, 'id="react-seconds"') && str_contains($html, 'id="react-character-photo"') && str_contains($html, 'id="react-tv-hint"') && str_contains($html, 'id="react-duplicates"') && $dialog['status'] === 200;
});
$wav = 'RIFF' . pack('V', 44) . 'WAVEfmt ' . pack('VvvVVvv', 16, 1, 1, 8000, 8000, 1, 8) . 'data' . pack('V', 8) . str_repeat("\x80", 8);
[$voiceBody, $voiceHeaders] = $multipart(['action' => 'upload', 'kind' => 'voice', 'character' => $maricotaId, 'label' => 'Voz'], 'voz.wav', 'audio/wav', $wav);
$voiceUpload = localRequest('api.php', 'POST', $voiceBody, array_merge($voiceHeaders, ['X-CSRF-Token: ' . $writeCsrf]));
$voiceData = json_decode($voiceUpload['body'], true);
$voiceServed = localRequest('media.php?id=' . ($voiceData['media']['id'] ?? 'x'));
[$badVoiceBody, $badVoiceHeaders] = $multipart(['action' => 'upload', 'kind' => 'voice', 'character' => $maricotaId], 'falso.mp3', 'audio/mpeg', '<?php echo 1;');
$badVoice = localRequest('api.php', 'POST', $badVoiceBody, array_merge($badVoiceHeaders, ['X-CSRF-Token: ' . $writeCsrf]));
check('the reference audio is validated, replaces the old one and is served only to the logged-in user with its own type', static fn(): bool => $voiceUpload['status'] === 201 && str_ends_with($voiceData['media']['id'] ?? '', '.wav') && $voiceServed['status'] === 200 && str_contains($voiceServed['headers']['content-type'] ?? '', 'audio/') && ($voiceServed['headers']['x-content-type-options'] ?? '') === 'nosniff'
    && $voiceServed['body'] === $wav && $badVoice['status'] === 400 && radar_character_voice_asset($maricotaId)['id'] === $voiceData['media']['id'] && localRequest('media.php?id=' . $voiceData['media']['id'], 'GET', '', [], false)['status'] !== 200);
$studioTv = localRequest('estudio.php?news=9001&roteiro=' . ($tvItem['id'] ?? 0));
check('the Studio opens a TV react with its three scenes as paragraphs and the TV-studio template available', static fn(): bool => $studioTv['status'] === 200 && str_contains($studioTv['body'], 'tv_studio') && str_contains($studioTv['body'], 'Cena um editada pelo usuário'));
$promptApi = $post(['action' => 'video_prompt', 'character' => $maricotaId, 'script_id' => $tvItem['id'] ?? 0, 'spoken' => "Primeira fala nova.\n\nSegunda fala nova.\n\nTerceira fala nova.", 'scenes' => 3, 'headline' => 'H', 'source' => 'S', 'layout' => 'tv_studio']);
$promptData = json_decode($promptApi['body'], true);
check('the Studio prompt API keeps the saved plan (emotion, camera, gestures) while the text is edited', static fn(): bool => $promptApi['status'] === 200 && ($promptData['scenes'] ?? 0) === 3 && str_contains($promptData['prompts'][1] ?? '', '"Segunda fala nova."') && str_contains($promptData['prompts'][1] ?? '', 'CAMERA FOR THIS SCENE: medium shot')
    && str_contains($promptData['prompts'][0] ?? '', 'SCENE 1 OF 3 (GANCHO)'));
$listPage = localRequest('roteiros.php');
check('the list of scripts identifies the character, the version of its configuration, the three scenes and the update', static fn(): bool => $listPage['status'] === 200 && str_contains($listPage['body'], 'Dona Maricota do Babado') && str_contains($listPage['body'], 'versão ' . (int)($tvItem['character_version'] ?? 0) . ' · 3 cenas') && str_contains($listPage['body'], 'atualização do #' . (int)($tvItem['id'] ?? 0)));
check('the script carries the article id and the source of the contract and the photo of a character is its master image', static function () use ($tvItem, $maricotaId): bool {
    $script = radar_script_get($tvItem['id']);
    $master = radar_db_value("SELECT id FROM media WHERE kind = 'reference' AND character_slug = ? AND role = 'master'", [$maricotaId]);
    return $script['article_id'] === 9001 && $script['article_source'] === 'https://quem.example.com/volta-aos-palcos' && $master === radar_character_photo_id($maricotaId) && radar_character_photo_id('clara') === (radar_character_primary('clara')['id'] ?? (radar_character_references('clara')[0]['id'] ?? null));
});
// Leave the private storage as the other tests expect it: only their own images.
foreach (radar_db_all("SELECT id FROM media WHERE character_slug = ? AND kind IN ('reference', 'voice')", [$maricotaId]) as $leftover) {
    radar_media_delete((string)$leftover['id']);
}
$mcpDuplicate = $call($reactToken, 'preparar_react', ['noticia_id' => 9001, 'personagem' => 'dona-maricota']);
$mcpForced = $call($reactToken, 'preparar_react', ['noticia_id' => 9001, 'personagem' => 'dona-maricota', 'gerar_mesmo_assim' => true]);
$mcpData = json_decode(json_decode($mcpForced['body'], true)['result']['content'][0]['text'] ?? '{}', true);
check('MCP warns about a news that already has a react and, when confirmed, returns the three scenes of the TV character', static fn(): bool => str_contains($mcpDuplicate['body'], 'Nada foi gerado') && str_contains($mcpDuplicate['body'], '"isError":true') && ($mcpData['personagem_id'] ?? '') === 'dona-maricota' && count($mcpData['cenas_detalhe'] ?? []) === 3
    && str_contains($mcpData['cenas_detalhe'][0]['prompt_para_o_muse'] ?? '', 'REGISTERED IN THE PANEL') && str_contains($mcpData['legendas_srt'] ?? '', '-->') && ($mcpData['titulo_do_video'] ?? '') !== '');
// Archiving through the API and the pages (and the Studio buttons).
$studioButtons = localRequest('estudio.php?news=9001&roteiro=' . ($tvItem['id'] ?? 0));
$copyAt = strpos($studioButtons['body'], 'id="st-copy-all"');
check('the Studio has one "Copiar todos os textos" above the scene texts, and buttons to copy or download the template image under the preview', static fn(): bool => $studioButtons['status'] === 200 && substr_count($studioButtons['body'], 'id="st-copy-all"') === 1 && $copyAt !== false && $copyAt < strpos($studioButtons['body'], 'id="st-prompts"')
    && $copyAt > strpos($studioButtons['body'], 'Prompts de vídeo') && str_contains($studioButtons['body'], 'id="st-copy-image"') && str_contains($studioButtons['body'], 'Copiar imagem do template') && str_contains($studioButtons['body'], 'id="st-download-image"')
    && strpos($studioButtons['body'], 'id="st-copy-image"') < strpos($studioButtons['body'], 'id="st-copy-all"') && !str_contains($studioButtons['body'], 'prancha + texto'));
$archiveCall = $post(['action' => 'character_archive', 'character' => 'beto']);
$archivedChips = localRequest('personagem.php?c=dona-maricota');
$archivedPage = localRequest('personagem.php?c=beto');
$archivedDialog = radar_react_dialog();
$archivedStudio = localRequest('estudio.php');
$lastOne = $post(['action' => 'character_archive', 'character' => 'clara']) ;
$lastTwo = $post(['action' => 'character_archive', 'character' => 'dona-maricota']);
$restoreCall = $post(['action' => 'character_restore', 'character' => 'beto']);
check('archiving Beto through the API removes him from the chips, the dialog and the Studio, keeps his page with a restore button, and restoring brings him back', static fn(): bool => $archiveCall['status'] === 200
    && !str_contains($archivedChips['body'], 'personagem.php?c=beto">Beto</a></div>') && str_contains($archivedChips['body'], 'Arquivados') && !str_contains($archivedDialog, 'value="beto"') && !str_contains($archivedStudio['body'], '"slug":"beto"')
    && $archivedPage['status'] === 200 && str_contains($archivedPage['body'], 'Restaurar no painel') && str_contains($archivedPage['body'], 'data-act="character_restore"') && str_contains($archivedChips['body'], 'data-act="character_archive"')
    && $restoreCall['status'] === 200 && in_array('beto', array_keys(radar_characters_visible()), true) && str_contains(radar_react_dialog(), 'value="beto"') && $post(['action' => 'character_archive', 'character' => 'inventado'])['status'] === 400);
radar_character_restore('clara');
radar_character_restore('dona-maricota');
// Category shortcuts of each character.
$shortcutStudio = localRequest('estudio.php');
$shortcutCharacter = localRequest('personagem.php?c=clara');
$shortcutNews = localRequest('noticias.php?categoria=' . rawurlencode('Cartão de crédito'));
check('the Studio carries the category shortcuts of every character and the character page links each category to its news', static fn(): bool => $shortcutStudio['status'] === 200 && str_contains($shortcutStudio['body'], 'id="st-category-box"') && str_contains($shortcutStudio['body'], 'id="st-category-links"')
    && str_contains($shortcutStudio['body'], '"slug":"clara"') && str_contains($shortcutStudio['body'], '"shortcuts":["Finanças","Cartão de crédito","Finanças pessoais","Economia"]') && str_contains($shortcutStudio['body'], '"shortcuts":["Famosos"')
    && $shortcutCharacter['status'] === 200 && str_contains($shortcutCharacter['body'], 'href="noticias.php?categoria=Finan%C3%A7as"') && str_contains($shortcutCharacter['body'], 'href="noticias.php?categoria=Cart%C3%A3o%20de%20cr%C3%A9dito"') && str_contains($shortcutCharacter['body'], 'Ir direto para as notícias de')
    && $shortcutNews['status'] === 200 && str_contains($shortcutNews['body'], 'aria-pressed="true"'));
