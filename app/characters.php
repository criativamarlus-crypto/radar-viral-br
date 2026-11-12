<?php
declare(strict_types=1);

/**
 * The characters of the panel. Each identity (the master prompt, the personality, the voice, the look it
 * starts from) is code, not data, so a character always keeps the same face and personality. What the panel
 * can change is the name shown and the categories a character covers; the images (reference sheet and
 * stills) are data, per character.
 *
 * Several characters can share the work: a news item suggests the character that covers its category, and
 * the one with no categories is the generalist that takes whatever is left.
 */

/** @return array<string,array<string,mixed>> slug => preset (the first one is the default character) */
function radar_characters(): array
{
    return [
        'beto' => [
            'name' => 'Beto',
            'gender' => 'm',
            'tagline' => 'O cara que comenta as notícias do dia',
            'bio' => 'Notícia boa, notícia ruim e notícia que bomba: eu comento do meu jeito. Opinião é opinião, fato é fato.',
            'identity_en' => 'Same recurring fictional Brazilian character in every shot: a Black Brazilian man, adult around 30 to 40 years old, heavyset and robust build, round face, short black hair, neat and discreet beard and mustache, modern dark-framed glasses, bright white and very expressive smile. Natural, charismatic, memorable look. He looks like an ordinary person recording a spontaneous vertical video on a phone (in a car, at home or in a small office), never like a TV news anchor. Keep his FACE and identity identical in every generation (face shape, features, skin tone, hair, beard, glasses and body build) and use the attached reference images only to preserve them. His clothes and accessories are not fixed: they follow the WARDROBE of each video.',
            'identity_pt' => 'Personagem fictício brasileiro, sempre com o mesmo rosto em todos os vídeos: homem negro brasileiro, adulto de aproximadamente 30 a 40 anos, corpo gordo e robusto, rosto arredondado, cabelo preto curto, barba e bigode discretos, óculos modernos de armação escura, sorriso branco e muito expressivo. Aparência natural, carismática e marcante. Parece uma pessoa comum gravando um vídeo espontâneo na vertical com o celular (no carro, em casa ou no escritório), nunca um apresentador de telejornal. Rosto, proporções e óculos são idênticos em todas as gerações, usando as imagens de referência enviadas só para preservar a identidade. A roupa e os acessórios não são fixos: mudam a cada notícia, combinando com o tom do assunto.',
            'personality' => [
                'Inteligente e antenado nos acontecimentos',
                'Espontâneo e comunicativo',
                'Engraçado e provocador, sem humilhar ninguém',
                'Expressivo, fala com linguagem popular brasileira',
                'Consegue comentar assuntos sérios com clareza e respeito',
            ],
            'voice' => 'Popular, espontâneo e brincalhão; frases curtas; humor sem humilhar ninguém; sério quando o assunto é grave.',
            'catchphrases' => ['Rapaz, tu viu isso aqui?', 'Agora me explica uma coisa!'],
            'pillars' => ['Notícias que bombam', 'Famosos e esportes', 'Curiosidades e assuntos virais'],
            'categories' => [],
            'expression' => 'sorriso-largo',
            'scene' => 'carro',
            'hooks' => ['Rapaz, tu viu isso aqui?', 'Agora me explica uma coisa!', 'Olha essa notícia que tá dando o que falar!'],
            'direct_hook' => 'Vamos entender essa notícia rapidinho.',
            'commentary' => 'Na minha opinião, esse é um assunto que merece a gente conversar com calma. Opinião minha, tá?',
            'cta' => 'E você, o que achou? Comenta aqui embaixo.',
            'default_outfit' => 'a plain black casual t-shirt',
            'default_accessories' => 'a thick gold chain necklace and a gold wristwatch',
            'outfits_extra' => [],
            'accessories' => [
                'serious' => ['a plain black watch and no other jewelry', 'a thin silver chain under the collar and a steel watch', 'a dark leather-strap watch', 'no jewelry at all, only a simple black watch'],
                'business' => ['a steel wristwatch with a brown leather strap and a thin silver chain', 'a minimalist silver watch and a small silver ring', 'a navy pocket square and a slim black watch', 'a thin gold chain and a classic gold-tone wristwatch', 'a leather bracelet and a steel watch'],
                'sport' => ['a black sports watch and a white sweatband on the wrist', 'a plain dark baseball cap with no logo, worn forward', 'a smartwatch with a black silicone strap', 'a thin sweatband on the forehead under the cap line and a sports watch'],
                'fun' => ['a chunky gold chain and a colorful beaded bracelet', 'wireless headphones resting around the neck and a bright wristband', 'a bucket hat in a solid color and a thick gold chain', 'a stack of colorful bracelets and a silver chain', 'a thick gold chain and a gold wristwatch'],
                'tech' => ['wireless earbuds in {his} ears and a smartwatch', 'a black smartwatch and a lanyard with a plain badge', 'a silver wristwatch and a small silver ring', 'over-ear headphones around the neck'],
                'casual' => ['a thick gold chain and a gold wristwatch', 'a black beanie and a plain watch', 'a leather bracelet and a silver watch', 'a dark baseball cap with no logo and a thin silver chain', 'no accessories except a simple watch'],
            ],
        ],
        'clara' => [
            'name' => 'Clara Contas',
            'gender' => 'f',
            'tagline' => 'Traduzindo o economês para o seu bolso',
            'bio' => 'Cartão de crédito, dívidas, score, Pix e investimentos explicados sem economês. Informação, não conselho: a decisão é sua. Bora fazer a conta?',
            'identity_en' => 'Same recurring fictional Brazilian character in every shot: a Brazilian woman, adult around 28 to 38 years old, medium build, light olive skin with a few freckles, long wavy chestnut-brown hair with soft caramel highlights worn loose past the shoulders, dark-framed rectangular glasses, a calm, warm and trustworthy expression with a gentle smile. Natural, smart, approachable look. She looks like an ordinary person recording a spontaneous vertical video on a phone (at home, in a small home office or at a cafe table), never like a TV news anchor. Keep her FACE and identity identical in every generation (face shape, features, skin tone, freckles, hair color and waves, glasses and body build) and use the attached reference images only to preserve them. Her clothes and accessories are not fixed: they follow the WARDROBE of each video.',
            'identity_pt' => 'Personagem fictícia brasileira, sempre com o mesmo rosto em todos os vídeos: mulher brasileira, adulta de aproximadamente 28 a 38 anos, corpo mediano, pele clara azeitonada com algumas sardas, cabelo castanho longo e ondulado com reflexos caramelo, solto abaixo dos ombros, óculos retangulares de armação escura, expressão calma, acolhedora e confiável, com sorriso suave. Aparência natural, inteligente e acessível. Parece uma pessoa comum gravando um vídeo espontâneo na vertical com o celular (em casa, num escritório pequeno ou numa mesa de café), nunca uma apresentadora de telejornal. Rosto, proporções e óculos são idênticos em todas as gerações, usando as imagens de referência enviadas só para preservar a identidade. A roupa e os acessórios não são fixos: mudam a cada notícia, combinando com o tom do assunto.',
            'personality' => [
                'Didática: explica dinheiro sem economês e traduz o jargão na hora',
                'Calma, confiável e direta, sem alarmismo',
                'Bem-humorada na medida, nunca debocha de quem está endividado',
                'Informa e não aconselha: não manda comprar, vender nem investir em nada',
                'Transforma números em exemplos do dia a dia e faz a conta em voz alta',
            ],
            'voice' => 'Clara, calma e próxima; frases curtas; exemplos do dia a dia; faz a conta em voz alta; evita jargão e, quando usa, explica na hora. Sempre separa informação de opinião e lembra que não é recomendação financeira.',
            'catchphrases' => ['Bora fazer a conta?', 'Traduzindo o economês:', 'Isso é informação, não é recomendação.'],
            'pillars' => ['Cartão de crédito: fatura, limite, juros, anuidade e milhas', 'Dívidas, score e renegociação', 'Investimentos explicados do zero', 'Golpes e Pix: como se proteger', 'Economia do dia a dia'],
            'categories' => ['Finanças', 'Cartão de crédito', 'Finanças pessoais', 'Economia'],
            'expression' => 'pensativo',
            'scene' => 'escritorio',
            'hooks' => ['Bora traduzir o economês?', 'Presta atenção, que essa mexe no seu bolso.', 'Deixa eu te explicar essa conta rapidinho.'],
            'direct_hook' => 'Vamos entender essa notícia, sem economês.',
            'commentary' => 'Na minha opinião, esse é um assunto para entender com calma. Opinião minha, tá?',
            'cta' => 'E você, já conferiu a sua fatura hoje? Conta aqui nos comentários.',
            'default_outfit' => 'a plain black fitted sleeveless top',
            'default_accessories' => 'small gold stud earrings',
            'outfits_extra' => [
                'serious' => ['a black fitted turtleneck', 'a dark grey tailored blazer over a black tank top', 'a deep navy blouse with a modest neckline', 'a charcoal knit cardigan over a black top'],
                'business' => ['a tailored camel blazer over a black fitted tank top', 'a crisp white silk blouse with a soft collar', 'a sage-green knit top under an open cream cardigan', 'a navy wrap blouse with three-quarter sleeves', 'a terracotta linen shirt with the sleeves rolled up'],
                'sport' => ['a plain athletic zip jacket in sky blue with no logos over a white tank top'],
                'fun' => ['a coral blouse with puffed sleeves', 'a teal knit top with a white collar', 'a lilac sweater with a relaxed fit'],
                'tech' => ['a heather grey sweatshirt with a quarter zip', 'a black tee under an olive utility jacket'],
                'casual' => ['a cream knit sweater', 'a light denim shirt over a white tank top', 'a soft sage green t-shirt with a relaxed fit', 'a mustard cardigan over a white tee'],
            ],
            'accessories' => [
                'serious' => ['small pearl stud earrings and a slim watch', 'thin gold hoop earrings and nothing else', 'a plain black watch and a thin gold necklace', 'tiny gold stud earrings'],
                'business' => ['delicate gold hoop earrings and a slim steel watch', 'a thin gold pendant necklace and small pearl studs', 'a silk scarf tied loosely at the neck and a leather-strap watch', 'a minimalist silver watch and small silver earrings', 'a thin gold bracelet and small gold hoops'],
                'sport' => ['a black smartwatch, small stud earrings and a plain cap with no logo', 'a sports watch and a simple hair tie on the wrist'],
                'fun' => ['oversized colorful earrings and a stack of thin bracelets', 'wireless earbuds and small gold hoops', 'a statement necklace in a bright color', 'tiny star earrings and a beaded bracelet'],
                'tech' => ['wireless earbuds and a smartwatch', 'small silver hoops and a black smartwatch', 'over-ear headphones around the neck'],
                'casual' => ['small gold hoops and a thin necklace', 'a woven bracelet and a silver watch', 'tiny stud earrings and a hair clip', 'a thin gold necklace and a slim watch'],
            ],
        ],
        'dona-maricota' => [
            'name' => 'Dona Maricota do Babado',
            'gender' => 'f',
            // "tv": a presenter in the official TV studio (not a phone selfie); "tv3": every react is three connected scenes.
            'style' => 'tv',
            'format' => 'tv3',
            'gallery' => true,
            'tagline' => 'A apresentadora que transforma notícia em babado',
            'bio' => 'Apresentadora de TV popular, 64 anos de pura curiosidade e carisma. Ela conta a notícia como quem conta um babado, sem inventar um fato sequer: a graça está no jeito, nunca na invenção.',
            'identity_en' => 'Same recurring fictional Brazilian TV host in every shot: DONA MARICOTA DO BABADO, a Brazilian woman who looks about 64 years old, with a robust build and natural body proportions, voluminous platinum-blonde hair worn teased up and curly, oversized cat-eye glasses exactly like the ones in the master reference image, bold makeup with red lipstick and expressive eyes, and red nails. She always wears a leopard-print blouse, large gold hoop earrings, a gold necklace and gold bangles. Her facial expressions are exaggerated, theatrical and memorable. The MASTER reference image is the official reference and has priority over any text description: keep her FACE, age, hair, glasses, leopard-print blouse and gold jewelry identical in every generation and use the attached reference images only to preserve them. Lighting, framing and camera may change from scene to scene, her physical identity never does. Never replace her with another woman and never imitate the face or identity of a real TV presenter.',
            'identity_pt' => 'Personagem fictícia brasileira, sempre a mesma em todos os vídeos: Dona Maricota do Babado, mulher brasileira com aparência de aproximadamente 64 anos, corpo robusto com proporções naturais, cabelos loiros platinados volumosos, armados e encaracolados, óculos grandes estilo gatinho exatamente como na imagem-mestre, maquiagem marcante com batom vermelho e olhos expressivos, unhas vermelhas. Usa sempre blusa de estampa de oncinha, brincos grandes dourados, colar e pulseiras douradas. As expressões faciais são exageradas, teatrais e memoráveis. A imagem-mestre é a referência oficial e vale mais do que qualquer descrição em texto: rosto, idade, cabelo, óculos, blusa e joias são idênticos em todas as gerações. Cores, iluminação, enquadramentos e câmera podem variar de cena para cena; a identidade física nunca. Nunca trocar por outra mulher nem imitar o rosto ou a identidade de apresentadores reais.',
            'personality' => [
                'Curiosa: tem a energia de uma vizinha fofoqueira com o carisma de apresentadora de programa popular',
                'Engraçada e teatral: exagera nas reações, nunca nos fatos',
                'Debochada sem agressividade gratuita',
                'Espontânea e surpreendente: improvisa comentários criativos sem inventar acontecimentos',
                'Comunicativa e expressiva, com linguagem popular brasileira',
                'Respeitosa e empática quando a notícia é grave: sem piada, sem deboche',
            ],
            'voice' => 'Voz feminina madura, levemente grave e encorpada, timbre levemente rouco porém claro, entonação de apresentadora popular brasileira. Fala espontânea, natural e expressiva: acelera discretamente na surpresa, faz pequenas pausas dramáticas e ri de vez em quando. Nunca soa robótica nem como locução publicitária.',
            'voice_en' => 'Mature Brazilian Portuguese female voice, slightly deep and full-bodied, slightly raspy but clear timbre, the intonation of a popular Brazilian TV host. Spontaneous, natural and expressive delivery: she speeds up a little when surprised, makes small dramatic pauses and laughs now and then. Never robotic, never a commercial voice-over. The exact same voice in every scene and in every video.',
            'catchphrases' => ['Minha filha...', 'Segura esse babado!', 'Eu não disse?', 'Isso aqui vai dar pano pra manga!', 'Meu povo, vocês viram isso?', 'Eu não sou de falar da vida de ninguém...', 'Rapaz, olha essa história!', 'Me conta uma coisa!'],
            'pillars' => ['Famosos e entretenimento', 'Política, com fatos e atribuições', 'Finanças e economia em linguagem popular', 'Notícias internacionais explicadas', 'Tecnologia sem complicação'],
            'categories' => [],
            'expression' => 'surpreso',
            'scene' => 'estudio',
            'scenery' => 'Estúdio de televisão popular brasileira: iluminação rosa, roxa e dourada, painéis luminosos, mesa de apresentadora, microfone, caneca personalizada, telão para manchetes e imagens da notícia e o letreiro "Dona Maricota do Babado". Visual alegre, chamativo e profissional, reconhecível em todos os episódios.',
            'scenery_en' => 'THE OFFICIAL STUDIO (the same in every episode): a popular Brazilian TV talk-show studio with pink, purple and gold lighting, glowing light panels with marquee bulbs, a glamorous presenter desk with a retro microphone and a white mug with a red-lips print, a big screen behind her for headlines and news images, and the lit sign "DONA MARICOTA DO BABADO". Cheerful, flashy and professional.',
            'negative_rules' => [
                'a different face, a different woman or a younger look-alike',
                'any change to her age, hair, glasses, leopard-print blouse or gold jewelry',
                'extra people, a co-host or an audience in the foreground',
                'invented lines, ad-libbed words or any speech that is not in the narration',
                'a robotic or advertising-style voice-over',
                'imitating the face, voice or identity of a real TV presenter',
                'subtitles or captions burned into the video',
                'logos, watermarks and brand names',
                'distorted hands or fingers',
            ],
            'narration' => ['scene_seconds' => 8, 'scenes' => 3],
            'hooks' => ['Minha filha, olha essa notícia!', 'Meu povo, vocês viram isso?', 'Rapaz, olha essa história!'],
            'direct_hook' => 'Vamos à notícia, meu povo.',
            'commentary' => 'Na minha opinião, esse assunto merece a gente conversar com calma. Opinião minha, tá?',
            'cta' => 'E você, o que achou? Me conta nos comentários.',
            'default_outfit' => 'a leopard-print blouse (the same one as in the master reference image)',
            'default_accessories' => 'large gold hoop earrings, a gold necklace, gold bangles and red nails',
            'fixed_look' => true,
            'outfits_extra' => [],
            'accessories' => [],
        ],
    ];
}

/**
 * The character shown first and used when none is chosen. The panel can change it ("Meus Personagens");
 * without a choice it is the first preset.
 */
function radar_character_default_slug(): string
{
    $chosen = (string)radar_setting('character_default', '');
    if ($chosen !== '' && isset(radar_characters()[$chosen]) && !radar_character_is_archived($chosen)) {
        return $chosen;
    }
    return (string)(array_key_first(radar_characters_visible()) ?? array_key_first(radar_characters()));
}

/**
 * Characters taken out of the panel ("arquivados"). They are not offered any more (no selector, no suggestion by category),
 * but what they made stays readable: older scripts and images keep their identity. Restoring brings them back.
 *
 * @return array<int,string> slugs
 */
function radar_character_archived(): array
{
    $decoded = json_decode((string)radar_setting('characters_archived', '[]'), true);
    return is_array($decoded) ? array_values(array_filter($decoded, static fn(mixed $slug): bool => is_string($slug) && isset(radar_characters()[$slug]))) : [];
}

function radar_character_is_archived(string $slug): bool
{
    return in_array($slug, radar_character_archived(), true);
}

/** The characters the panel offers. @return array<string,array<string,mixed>> slug => preset */
function radar_characters_visible(): array
{
    $archived = radar_character_archived();
    return array_filter(radar_characters(), static fn(string $slug): bool => !in_array($slug, $archived, true), ARRAY_FILTER_USE_KEY);
}

/** A character that can be chosen for a new react: it exists and is not archived. */
function radar_character_selectable(?string $slug): bool
{
    return $slug !== null && isset(radar_characters_visible()[$slug]);
}

/** Take a character out of the panel. At least one must stay; when it was the default, the first visible one takes over. */
function radar_character_archive(string $slug): void
{
    if (!radar_character_valid($slug)) {
        throw new InvalidArgumentException('Personagem não encontrado.');
    }
    if (radar_character_is_archived($slug)) {
        return;
    }
    if (count(radar_characters_visible()) <= 1) {
        throw new InvalidArgumentException('Precisa sobrar pelo menos um personagem no painel.');
    }
    radar_setting_set('characters_archived', json_encode(array_values(array_unique(array_merge(radar_character_archived(), [$slug])))));
    if ((string)radar_setting('character_default', '') === $slug) {
        radar_setting_set('character_default', (string)array_key_first(radar_characters_visible()));
    }
}

function radar_character_restore(string $slug): void
{
    if (!radar_character_valid($slug)) {
        throw new InvalidArgumentException('Personagem não encontrado.');
    }
    radar_setting_set('characters_archived', json_encode(array_values(array_diff(radar_character_archived(), [$slug]))));
}

/** Make a character the default one (selected first in the Studio and used for categories nobody covers). */
function radar_character_default_set(string $slug): void
{
    if (!radar_character_selectable($slug)) {
        throw new InvalidArgumentException('Personagem não encontrado.');
    }
    radar_setting_set('character_default', $slug);
}

function radar_character_valid(string $slug): bool
{
    return isset(radar_characters()[$slug]);
}

/** Unknown or empty slugs fall back to the default character. */
function radar_character_slug(?string $slug): string
{
    return $slug !== null && radar_character_valid($slug) ? $slug : radar_character_default_slug();
}

/**
 * A character: the code preset plus what the panel may change (display name, categories and the registered
 * profile: description, personality, voice, catchphrases, scenery, negative rules and narration settings).
 *
 * @return array<string,mixed>
 */
function radar_character_get(?string $slug = null): array
{
    $slug = radar_character_slug($slug);
    $preset = radar_characters()[$slug];
    $name = trim(radar_setting('character_name_' . $slug, ''));
    $preset['slug'] = $slug;
    $preset['name'] = $name !== '' ? $name : $preset['name'];
    $preset['categories'] = radar_character_categories($slug);
    $profile = radar_character_profile($slug);
    foreach (radar_character_profile_text_fields() as $field => $limit) {
        if (isset($profile[$field]) && is_string($profile[$field]) && $profile[$field] !== '') {
            $preset[$field] = $profile[$field];
        }
    }
    foreach (radar_character_profile_list_fields() as $field => $limits) {
        if (isset($profile[$field]) && is_array($profile[$field]) && $profile[$field] !== []) {
            $preset[$field] = $profile[$field];
        }
    }
    $narration = radar_character_narration($slug, $profile);
    $preset['narration'] = $narration;
    $preset['version'] = radar_character_version($slug);
    return $preset;
}

/** Permanent identifier of a character, used in exports and the API (the slug never changes). */
function radar_character_id(?string $slug = null): string
{
    return radar_character_slug($slug);
}

/** Text fields of the registered profile, with their size limits. @return array<string,int> */
function radar_character_profile_text_fields(): array
{
    return ['identity_pt' => 2500, 'identity_en' => 2500, 'voice' => 900, 'voice_en' => 900, 'scenery' => 900, 'scenery_en' => 900, 'bio' => 600, 'tagline' => 120];
}

/** List fields of the registered profile: [max items, max length of each]. @return array<string,array{0:int,1:int}> */
function radar_character_profile_list_fields(): array
{
    return ['personality' => [10, 200], 'catchphrases' => [16, 80], 'negative_rules' => [20, 200]];
}

/** What the panel registered for a character (raw), or []. @return array<string,mixed> */
function radar_character_profile(string $slug): array
{
    $stored = radar_db_value('SELECT data FROM character_profiles WHERE slug = ?', [$slug]);
    $decoded = is_string($stored) ? json_decode($stored, true) : null;
    return is_array($decoded) ? $decoded : [];
}

/** Version of the configuration of a character: 1 until something is changed, then +1 on every real change. */
function radar_character_version(string $slug): int
{
    return max(1, (int)radar_db_value('SELECT version FROM character_profiles WHERE slug = ?', [$slug]));
}

/** Count a change of the configuration (name, categories, profile, voice, images). */
function radar_character_bump_version(string $slug): int
{
    $now = time();
    if (radar_db_value('SELECT 1 FROM character_profiles WHERE slug = ?', [$slug]) === null) {
        radar_db_exec("INSERT INTO character_profiles (slug, version, data, updated_at) VALUES (?, 2, '{}', ?)", [$slug, $now]);
        return 2;
    }
    radar_db_exec('UPDATE character_profiles SET version = version + 1, updated_at = ? WHERE slug = ?', [$now, $slug]);
    return radar_character_version($slug);
}

/**
 * Narration settings: seconds per scene (4 to 15) and scenes per react (tv characters always make 3).
 *
 * @return array{scene_seconds:int,scenes:int,words_per_second:float}
 */
function radar_character_narration(string $slug, ?array $profile = null): array
{
    $profile ??= radar_character_profile($slug);
    $base = radar_characters()[$slug]['narration'] ?? ['scene_seconds' => 8, 'scenes' => 3];
    $stored = is_array($profile['narration'] ?? null) ? $profile['narration'] : [];
    return [
        'scene_seconds' => max(4, min(15, (int)($stored['scene_seconds'] ?? $base['scene_seconds']))),
        'scenes' => 3,
        'words_per_second' => 2.5,
    ];
}

/** "tv3": every react is three connected scenes in the TV studio; "classic": the phone-selfie react. */
function radar_character_format(?string $slug = null): string
{
    return (string)(radar_characters()[radar_character_slug($slug)]['format'] ?? 'classic');
}

/**
 * Save the registered profile. Only the fields that arrive are changed; an empty text goes back to the code default.
 * The version goes up by one when something really changed. Returns the (new) version.
 *
 * @param array<string,mixed> $input text fields as strings; list fields as arrays or one item per line; scene_seconds as int
 */
function radar_character_profile_save(string $slug, array $input): int
{
    if (!radar_character_valid($slug)) {
        throw new InvalidArgumentException('Personagem não encontrado.');
    }
    $profile = radar_character_profile($slug);
    $before = $profile;
    $clean = static fn(string $text): string => trim(preg_replace('/[ \t]+/u', ' ', preg_replace('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/u', '', strip_tags($text)) ?? '') ?? '');
    foreach (radar_character_profile_text_fields() as $field => $limit) {
        if (!array_key_exists($field, $input)) {
            continue;
        }
        $value = $clean((string)$input[$field]);
        if (mb_strlen($value) > $limit) {
            throw new InvalidArgumentException('O campo "' . $field . '" aceita até ' . $limit . ' caracteres.');
        }
        if ($value === '' || $value === (string)(radar_characters()[$slug][$field] ?? '')) {
            unset($profile[$field]);
        } else {
            $profile[$field] = $value;
        }
    }
    foreach (radar_character_profile_list_fields() as $field => [$maxItems, $maxLength]) {
        if (!array_key_exists($field, $input)) {
            continue;
        }
        $items = is_array($input[$field]) ? $input[$field] : (preg_split('/\R/u', (string)$input[$field]) ?: []);
        $list = [];
        foreach ($items as $item) {
            $text = $clean((string)$item);
            if ($text !== '') {
                if (mb_strlen($text) > $maxLength) {
                    throw new InvalidArgumentException('Cada item de "' . $field . '" aceita até ' . $maxLength . ' caracteres.');
                }
                $list[] = $text;
            }
        }
        $list = array_slice(array_values(array_unique($list)), 0, $maxItems);
        if ($list === [] || $list === array_values((array)(radar_characters()[$slug][$field] ?? []))) {
            unset($profile[$field]);
        } else {
            $profile[$field] = $list;
        }
    }
    if (isset($input['scene_seconds'])) {
        $seconds = (int)$input['scene_seconds'];
        if ($seconds < 4 || $seconds > 15) {
            throw new InvalidArgumentException('A duração de cada cena fica entre 4 e 15 segundos.');
        }
        if ($seconds === (int)(radar_characters()[$slug]['narration']['scene_seconds'] ?? 8)) {
            unset($profile['narration']);
        } else {
            $profile['narration'] = ['scene_seconds' => $seconds];
        }
    }
    if ($profile === $before) {
        return radar_character_version($slug);
    }
    $now = time();
    $json = json_encode($profile, JSON_UNESCAPED_UNICODE) ?: '{}';
    if (radar_db_value('SELECT 1 FROM character_profiles WHERE slug = ?', [$slug]) === null) {
        radar_db_exec('INSERT INTO character_profiles (slug, version, data, updated_at) VALUES (?, 2, ?, ?)', [$slug, $json, $now]);
    } else {
        radar_db_exec('UPDATE character_profiles SET data = ?, version = version + 1, updated_at = ? WHERE slug = ?', [$json, $now, $slug]);
    }
    return radar_character_version($slug);
}

/** Categories a character covers (empty = the generalist: everything no one else covers). @return array<int,string> */
function radar_character_categories(string $slug): array
{
    $stored = radar_setting('character_categories_' . $slug, '');
    if ($stored !== '') {
        $decoded = json_decode($stored, true);
        if (is_array($decoded)) {
            return array_values(array_filter(radar_category_list(), static fn(string $c): bool => in_array($c, $decoded, true)));
        }
    }
    return radar_characters()[$slug]['categories'] ?? [];
}

/** Save what the panel may change. Names are plain text of up to 40 characters. */
function radar_character_save(string $slug, string $name, array $categories): void
{
    if (!radar_character_valid($slug)) {
        throw new InvalidArgumentException('Personagem não encontrado.');
    }
    $name = trim(preg_replace('/\s+/u', ' ', strip_tags($name)) ?? '');
    if ($name === '' || mb_strlen($name) > 40) {
        throw new InvalidArgumentException('Informe um nome de até 40 caracteres.');
    }
    $valid = array_values(array_filter(radar_category_list(), static fn(string $c): bool => in_array($c, $categories, true)));
    $changed = radar_character_get($slug)['name'] !== $name || radar_character_categories($slug) !== $valid;
    radar_setting_set('character_name_' . $slug, $name);
    radar_setting_set('character_categories_' . $slug, json_encode($valid, JSON_UNESCAPED_UNICODE));
    if ($changed) {
        radar_character_bump_version($slug);
    }
}

/** The character that covers a category: the one listing it, else the generalist, else the default. */
function radar_character_for_category(string $category): string
{
    // The default character is looked at first, so when it is a generalist it takes what nobody covers.
    $order = array_values(array_unique(array_merge([radar_character_default_slug()], array_keys(radar_characters_visible()))));
    $generalist = null;
    foreach ($order as $slug) {
        $categories = radar_character_categories($slug);
        if (in_array($category, $categories, true)) {
            return $slug;
        }
        if ($categories === [] && $generalist === null) {
            $generalist = $slug;
        }
    }
    return $generalist ?? radar_character_default_slug();
}

/** Pronouns and nouns for the prompts, so one text serves a man and a woman. @return array<string,string> */
function radar_character_pronouns(?string $slug = null): array
{
    if (radar_character_get($slug)['gender'] === 'f') {
        return ['he' => 'she', 'He' => 'She', 'his' => 'her', 'His' => 'Her', 'him' => 'her', 'man' => 'woman', 'ele' => 'ela', 'Ele' => 'Ela', 'homem' => 'mulher', 'dele' => 'dela'];
    }
    return ['he' => 'he', 'He' => 'He', 'his' => 'his', 'His' => 'His', 'him' => 'him', 'man' => 'man', 'ele' => 'ele', 'Ele' => 'Ele', 'homem' => 'homem', 'dele' => 'dele'];
}

/** Replace {he}, {his}, {him}, {man}, {He}, {His}, {ele}, {Ele}, {homem} and {dele} for the character. */
function radar_character_words(string $text, ?string $slug = null): string
{
    $map = [];
    foreach (radar_character_pronouns($slug) as $token => $word) {
        $map['{' . $token . '}'] = $word;
    }
    return strtr($text, $map);
}
