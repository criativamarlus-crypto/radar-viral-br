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
    ];
}

function radar_character_default_slug(): string
{
    return (string)array_key_first(radar_characters());
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
 * A character: the code preset plus what the panel may change (display name and categories).
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
    return $preset;
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
    radar_setting_set('character_name_' . $slug, $name);
    radar_setting_set('character_categories_' . $slug, json_encode($valid, JSON_UNESCAPED_UNICODE));
}

/** The character that covers a category: the one listing it, else the generalist, else the default. */
function radar_character_for_category(string $category): string
{
    $generalist = null;
    foreach (array_keys(radar_characters()) as $slug) {
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
