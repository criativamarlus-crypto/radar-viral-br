<?php
declare(strict_types=1);

/** Archiving a character takes it out of the panel without losing what it made. Included by run.php. */
check('an archived character leaves the lists and the suggestions, cannot be chosen, and the default moves away from it', static function (): bool {
    $old = radar_react_generate(701, ['character' => 'beto', 'scenes' => 1]);
    radar_character_default_set('beto');
    radar_character_archive('beto');
    $visible = array_keys(radar_characters_visible());
    $ok = $visible === ['clara', 'dona-maricota'] && radar_character_archived() === ['beto'] && radar_character_is_archived('beto') && !radar_character_selectable('beto') && radar_character_selectable('clara') && radar_character_valid('beto')
        && radar_character_default_slug() === 'clara' && radar_character_for_category('Política') === 'dona-maricota' && rejects(static fn() => radar_character_default_set('beto'), InvalidArgumentException::class);
    $chosen = radar_react_generate(701, ['character' => 'beto', 'scenes' => 1, 'on_duplicate' => 'allow']);
    $switch = radar_script_update($old['id'], ['character' => 'beto', 'hook' => 'Gancho com personagem arquivado.']);
    // what the archived character made is still readable with its own identity
    $reread = radar_script_get($old['id']);
    $stillBeto = $reread['character'] === 'beto' && $reread['character_name'] === 'Beto' && str_contains($reread['video_prompt'], 'Black Brazilian man') && $switch['character'] === 'beto';
    radar_character_restore('beto');
    radar_setting_set('character_default', '');
    return $ok && $chosen['character'] !== 'beto' && $stillBeto && radar_character_archived() === [] && radar_character_selectable('beto') && array_keys(radar_characters_visible()) === ['beto', 'clara', 'dona-maricota'];
});
check('the last visible character cannot be archived, an unknown one is refused, and archiving twice changes nothing', static function (): bool {
    radar_character_archive('beto');
    radar_character_archive('beto');
    radar_character_archive('clara');
    $last = rejects(static fn() => radar_character_archive('dona-maricota'), InvalidArgumentException::class);
    $unknown = rejects(static fn() => radar_character_archive('inventado'), InvalidArgumentException::class) && rejects(static fn() => radar_character_restore('inventado'), InvalidArgumentException::class);
    $archived = radar_character_archived();
    $during = radar_character_default_slug();
    radar_character_restore('clara');
    radar_character_restore('beto');
    radar_setting_set('character_default', '');
    return $last && $unknown && $archived === ['beto', 'clara'] && radar_character_archived() === [] && $during === 'dona-maricota' && radar_character_default_slug() === 'beto';
});
check('the generate dialog does not offer an archived character', static function (): bool {
    radar_character_archive('beto');
    $html = radar_react_dialog();
    radar_character_restore('beto');
    return !str_contains($html, 'value="beto"') && str_contains($html, 'value="clara"') && str_contains($html, 'value="dona-maricota"') && str_contains(radar_react_dialog(), 'value="beto"');
});

check('every character has up to four category shortcuts: the ones it is listed under first, then related ones, all real categories', static function (): bool {
    $clara = radar_character_shortcuts('clara');
    radar_character_save('dona-maricota', 'Dona Maricota do Babado', ['Famosos', 'Entretenimento']);
    $maricota = radar_character_shortcuts('dona-maricota');
    radar_character_save('dona-maricota', 'Dona Maricota do Babado', ['Política']);
    $edited = radar_character_shortcuts('dona-maricota');
    radar_character_save('dona-maricota', 'Dona Maricota do Babado', []);
    $none = radar_character_shortcuts('dona-maricota');
    foreach (array_keys(radar_characters()) as $slug) {
        $list = radar_character_shortcuts($slug);
        if (count($list) < 1 || count($list) > 4 || count(array_unique($list)) !== count($list) || array_diff($list, radar_category_list()) !== []) {
            return false;
        }
    }
    return $clara === ['Finanças', 'Cartão de crédito', 'Finanças pessoais', 'Economia'] && $maricota === ['Famosos', 'Entretenimento', 'Neymar', 'Assuntos virais']
        && $edited === ['Política', 'Famosos', 'Entretenimento', 'Neymar'] && $none === ['Famosos', 'Entretenimento', 'Neymar', 'Assuntos virais'] && radar_character_shortcuts('beto') === ['Futebol', 'Neymar', 'Esportes', 'Política'];
});
