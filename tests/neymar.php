<?php
declare(strict_types=1);

/** The "Neymar" category: a subject, not a topic. Every story that names him goes there, from any outlet. Included by run.php. */
check('the Neymar category exists, comes right after football and takes every headline or summary that names him', static function (): bool {
    $list = radar_category_list();
    return in_array('Neymar', $list, true) && $list[array_search('Futebol', $list, true) + 1] === 'Neymar' && radar_source_category_valid('Neymar')
        && radar_classify('Neymar volta a treinar no Santos') === 'Neymar' && radar_classify('NEYMAR, o craque, diz que fica no Santos') === 'Neymar' && radar_classify("Pai de Neymar é o novo CEO do Santos") === 'Neymar'
        && radar_classify('Bruna Biancardi mostra ensaio em família', 'Ela posou ao lado de Neymar e das filhas.') === 'Neymar' && radar_classify('Neymar processa site por boato', '', 'Famosos') === 'Neymar'
        && radar_classify('Mansão de luxo vira briga na Justiça', 'Richarlison e Neymar trocam farpas.', 'Política') === 'Neymar' && radar_classify('Neymar', '', 'Neymar') === 'Neymar';
});
check('only him: other players, a similar word or a plain football story are not Neymar', static function (): bool {
    return radar_classify('Santos vence o Coritiba por 2 a 1 no Brasileirão') === 'Futebol' && radar_classify('Neymarzinho é o apelido do menino') !== 'Neymar' && radar_classify('Vini Jr. brilha em jogo pela Champions') !== 'Neymar'
        && radar_classify('Cantora anuncia turnê', 'A artista confirmou a turnê.', 'Famosos') === 'Famosos';
});
check('the seed brings the three Neymar feeds, sorts the stored news again once, and a Neymar story moves out of football and gossip', static function (): bool {
    global $now;
    radar_setting_set('sources_seeded_version', '3');
    radar_db_exec("DELETE FROM sources WHERE url LIKE '%q=Neymar%'");
    radar_db_exec("INSERT INTO news (id, uid, source_name, title, url, summary, published_at, first_seen_at, last_seen_at, category, score, source_count) VALUES (830, 'ney1', 'GE', 'Neymar volta a treinar no Santos', 'https://ge.example.com/ney1', 'Treino aberto.', ?, ?, ?, 'Futebol', 50, 1)", [$now - 600, $now - 600, $now - 600]);
    radar_db_exec("INSERT INTO news (id, uid, source_name, title, url, summary, published_at, first_seen_at, last_seen_at, category, score, source_count) VALUES (831, 'ney2', 'Quem', 'Modelo mostra ensaio em família', 'https://quem.example.com/ney2', 'Posou ao lado de Neymar e das filhas.', ?, ?, ?, 'Famosos', 50, 1)", [$now - 600, $now - 600, $now - 600]);
    radar_db_exec("INSERT INTO news (id, uid, source_name, title, url, summary, published_at, first_seen_at, last_seen_at, category, score, source_count) VALUES (832, 'ney3', 'GE', 'Santos recebe o Coritiba', 'https://ge.example.com/ney3', 'Jogo no sábado.', ?, ?, ?, 'Futebol', 50, 1)", [$now - 600, $now - 600, $now - 600]);
    $added = radar_sources_seed();
    $again = radar_sources_seed();
    $feeds = radar_db_all("SELECT name, category, weight, active FROM sources WHERE url LIKE '%q=Neymar%'");
    $items = radar_news_query(radar_news_filters(['categoria' => 'Neymar', 'janela' => 'tudo', 'agrupar' => '0']))['items'];
    $ids = array_column($items, 'id');
    return $added >= 3 && $again === 0 && count($feeds) === 3 && array_unique(array_column($feeds, 'category')) === ['Neymar'] && array_sum(array_column($feeds, 'active')) === 3
        && radar_setting('sources_seeded_version') === RADAR_SOURCES_SEED_VERSION && RADAR_SOURCES_SEED_VERSION === '4'
        && in_array(830, $ids, true) && in_array(831, $ids, true) && !in_array(832, $ids, true) && (radar_news_category_counts(radar_news_filters(['janela' => 'tudo', 'agrupar' => '0']))['Neymar'] ?? 0) >= 2;
});
check('a Neymar story is a good react candidate: interest in the category and the react-friendly list', static function (): bool {
    return radar_rank_category_interest()['Neymar'] === 9 && radar_direction_mood('Neymar') === 'sport' && radar_character_for_category('Neymar') === 'beto';
});
