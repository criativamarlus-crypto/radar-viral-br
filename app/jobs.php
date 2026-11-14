<?php
declare(strict_types=1);

/**
 * The "Vagas de emprego" category: real job announcements published by real outlets (the link goes to the original
 * article), with the place (region, state, city) and the age group read from the text of each one.
 *
 * Nothing here invents a vacancy: a story is a vacancy story only when its headline or summary says so, and the place and
 * the age are filled only when the text names them. A story that names no place stays without one ("Brasil todo").
 */

const RADAR_JOB_CATEGORY = 'Vagas de emprego';

/** @return array<int,string> */
function radar_job_regions(): array
{
    return ['Norte', 'Nordeste', 'Centro-Oeste', 'Sudeste', 'Sul', 'Paraguai'];
}

/** @return array<int,string> the five regions of Brazil */
function radar_job_brazil_regions(): array
{
    return ['Norte', 'Nordeste', 'Centro-Oeste', 'Sudeste', 'Sul'];
}

/** The departments of Paraguay (ISO 3166-2:PY codes) with the capital district. @return array<string,string> code => name */
function radar_job_py_departments(): array
{
    return [
        'PY-ASU' => 'Asunción', 'PY-1' => 'Concepción', 'PY-2' => 'San Pedro', 'PY-3' => 'Cordillera', 'PY-4' => 'Guairá', 'PY-5' => 'Caaguazú', 'PY-6' => 'Caazapá', 'PY-7' => 'Itapúa', 'PY-8' => 'Misiones',
        'PY-9' => 'Paraguarí', 'PY-10' => 'Alto Paraná', 'PY-11' => 'Central', 'PY-12' => 'Ñeembucú', 'PY-13' => 'Amambay', 'PY-14' => 'Canindeyú', 'PY-15' => 'Presidente Hayes', 'PY-16' => 'Alto Paraguay', 'PY-19' => 'Boquerón',
    ];
}

/** Every state of Brazil and department of Paraguay, for the filters. @return array<string,array{name:string,region:string}> */
function radar_job_all_states(): array
{
    $all = radar_job_states();
    foreach (radar_job_py_departments() as $code => $name) {
        $all[$code] = ['name' => $name, 'region' => 'Paraguai'];
    }
    return $all;
}

/** What is shown for a state code: the UF of Brazil or the name of the Paraguayan department. */
function radar_job_state_label(string $code): string
{
    return radar_job_py_departments()[$code] ?? $code;
}

/** Cities of Paraguay (the biggest, by department). @return array<string,string> city => department code */
function radar_job_py_cities(): array
{
    return [
        'Asunción' => 'PY-ASU', 'Luque' => 'PY-11', 'San Lorenzo' => 'PY-11', 'Lambaré' => 'PY-11', 'Fernando de la Mora' => 'PY-11', 'Capiatá' => 'PY-11', 'Limpio' => 'PY-11', 'Ñemby' => 'PY-11', 'Villa Elisa' => 'PY-11',
        'Mariano Roque Alonso' => 'PY-11', 'Itauguá' => 'PY-11', 'Areguá' => 'PY-11', 'Ypacaraí' => 'PY-11', 'Ypané' => 'PY-11', 'Guarambaré' => 'PY-11', 'Villeta' => 'PY-11', 'San Antonio' => 'PY-11',
        'Ciudad del Este' => 'PY-10', 'Hernandarias' => 'PY-10', 'Presidente Franco' => 'PY-10', 'Minga Guazú' => 'PY-10', 'Santa Rita' => 'PY-10', 'Encarnación' => 'PY-7', 'Cambyretá' => 'PY-7', 'Hohenau' => 'PY-7',
        'Pedro Juan Caballero' => 'PY-13', 'Salto del Guairá' => 'PY-14', 'Katueté' => 'PY-14', 'Curuguaty' => 'PY-14', 'Coronel Oviedo' => 'PY-5', 'Caaguazú' => 'PY-5', 'Villarrica' => 'PY-4', 'Concepción' => 'PY-1',
        'San Juan Bautista' => 'PY-8', 'San Ignacio' => 'PY-8', 'Ayolas' => 'PY-8', 'Pilar' => 'PY-12', 'Villa Hayes' => 'PY-15', 'Filadelfia' => 'PY-19', 'Loma Plata' => 'PY-19', 'Caacupé' => 'PY-3', 'Paraguarí' => 'PY-9',
        'Carapeguá' => 'PY-9', 'Caazapá' => 'PY-6', 'Fuerte Olimpo' => 'PY-16', 'Santaní' => 'PY-2',
    ];
}

/** City names that, written in a text, already say it is about Paraguay. @return array<int,string> */
function radar_job_py_markers(): array
{
    return ['Asunción', 'Ciudad del Este', 'Encarnación', 'Pedro Juan Caballero', 'Lambaré', 'Fernando de la Mora', 'Capiatá', 'Luque', 'Minga Guazú', 'Hernandarias', 'Presidente Franco', 'Villa Elisa', 'Ñemby', 'Mariano Roque Alonso', 'Coronel Oviedo', 'Salto del Guairá', 'Villa Hayes', 'Caacupé'];
}

/** True when the text is about Paraguay: the country's name or a city that only exists there. */
function radar_job_is_paraguay(string $foldedText): bool
{
    if (preg_match('/ (paraguay|paraguai|paraguayo|paraguaya|paraguaio|paraguaia|paraguayos|paraguaios) /', $foldedText) === 1) {
        return true;
    }
    foreach (radar_job_py_markers() as $marker) {
        if (str_contains($foldedText, ' ' . radar_fold($marker) . ' ')) {
            return true;
        }
    }
    return false;
}

/** @return array<string,array{name:string,region:string}> UF => state */
function radar_job_states(): array
{
    return [
        'AC' => ['name' => 'Acre', 'region' => 'Norte'], 'AL' => ['name' => 'Alagoas', 'region' => 'Nordeste'], 'AP' => ['name' => 'Amapá', 'region' => 'Norte'],
        'AM' => ['name' => 'Amazonas', 'region' => 'Norte'], 'BA' => ['name' => 'Bahia', 'region' => 'Nordeste'], 'CE' => ['name' => 'Ceará', 'region' => 'Nordeste'],
        'DF' => ['name' => 'Distrito Federal', 'region' => 'Centro-Oeste'], 'ES' => ['name' => 'Espírito Santo', 'region' => 'Sudeste'], 'GO' => ['name' => 'Goiás', 'region' => 'Centro-Oeste'],
        'MA' => ['name' => 'Maranhão', 'region' => 'Nordeste'], 'MT' => ['name' => 'Mato Grosso', 'region' => 'Centro-Oeste'], 'MS' => ['name' => 'Mato Grosso do Sul', 'region' => 'Centro-Oeste'],
        'MG' => ['name' => 'Minas Gerais', 'region' => 'Sudeste'], 'PA' => ['name' => 'Pará', 'region' => 'Norte'], 'PB' => ['name' => 'Paraíba', 'region' => 'Nordeste'],
        'PR' => ['name' => 'Paraná', 'region' => 'Sul'], 'PE' => ['name' => 'Pernambuco', 'region' => 'Nordeste'], 'PI' => ['name' => 'Piauí', 'region' => 'Nordeste'],
        'RJ' => ['name' => 'Rio de Janeiro', 'region' => 'Sudeste'], 'RN' => ['name' => 'Rio Grande do Norte', 'region' => 'Nordeste'], 'RS' => ['name' => 'Rio Grande do Sul', 'region' => 'Sul'],
        'RO' => ['name' => 'Rondônia', 'region' => 'Norte'], 'RR' => ['name' => 'Roraima', 'region' => 'Norte'], 'SC' => ['name' => 'Santa Catarina', 'region' => 'Sul'],
        'SP' => ['name' => 'São Paulo', 'region' => 'Sudeste'], 'SE' => ['name' => 'Sergipe', 'region' => 'Nordeste'], 'TO' => ['name' => 'Tocantins', 'region' => 'Norte'],
    ];
}

/** The 27 capitals and the biggest other cities. @return array<string,string> city => UF */
function radar_job_cities(): array
{
    return [
        'Rio Branco' => 'AC', 'Maceió' => 'AL', 'Macapá' => 'AP', 'Manaus' => 'AM', 'Salvador' => 'BA', 'Fortaleza' => 'CE', 'Brasília' => 'DF', 'Vitória' => 'ES', 'Goiânia' => 'GO',
        'São Luís' => 'MA', 'Cuiabá' => 'MT', 'Campo Grande' => 'MS', 'Belo Horizonte' => 'MG', 'Belém' => 'PA', 'João Pessoa' => 'PB', 'Curitiba' => 'PR', 'Recife' => 'PE',
        'Teresina' => 'PI', 'Rio de Janeiro' => 'RJ', 'Natal' => 'RN', 'Porto Alegre' => 'RS', 'Porto Velho' => 'RO', 'Boa Vista' => 'RR', 'Florianópolis' => 'SC',
        'São Paulo' => 'SP', 'Aracaju' => 'SE', 'Palmas' => 'TO',
        'Campinas' => 'SP', 'Guarulhos' => 'SP', 'Santos' => 'SP', 'São Bernardo do Campo' => 'SP', 'Santo André' => 'SP', 'Osasco' => 'SP', 'Sorocaba' => 'SP', 'Ribeirão Preto' => 'SP',
        'São José dos Campos' => 'SP', 'Jundiaí' => 'SP', 'Piracicaba' => 'SP', 'Bauru' => 'SP', 'São José do Rio Preto' => 'SP', 'Mauá' => 'SP', 'Barueri' => 'SP', 'Franca' => 'SP',
        'Niterói' => 'RJ', 'Duque de Caxias' => 'RJ', 'Nova Iguaçu' => 'RJ', 'São Gonçalo' => 'RJ', 'Campos dos Goytacazes' => 'RJ', 'Petrópolis' => 'RJ', 'Volta Redonda' => 'RJ', 'Macaé' => 'RJ',
        'Contagem' => 'MG', 'Uberlândia' => 'MG', 'Juiz de Fora' => 'MG', 'Betim' => 'MG', 'Montes Claros' => 'MG', 'Uberaba' => 'MG', 'Ipatinga' => 'MG', 'Governador Valadares' => 'MG',
        'Serra' => 'ES', 'Vila Velha' => 'ES', 'Cariacica' => 'ES', 'Linhares' => 'ES',
        'Joinville' => 'SC', 'Blumenau' => 'SC', 'Itajaí' => 'SC', 'Chapecó' => 'SC', 'Criciúma' => 'SC', 'Balneário Camboriú' => 'SC',
        'Londrina' => 'PR', 'Maringá' => 'PR', 'Ponta Grossa' => 'PR', 'Cascavel' => 'PR', 'Foz do Iguaçu' => 'PR', 'São José dos Pinhais' => 'PR',
        'Caxias do Sul' => 'RS', 'Pelotas' => 'RS', 'Canoas' => 'RS', 'Santa Maria' => 'RS', 'Gravataí' => 'RS', 'Novo Hamburgo' => 'RS',
        'Feira de Santana' => 'BA', 'Vitória da Conquista' => 'BA', 'Camaçari' => 'BA', 'Ilhéus' => 'BA', 'Juazeiro' => 'BA',
        'Caruaru' => 'PE', 'Petrolina' => 'PE', 'Olinda' => 'PE', 'Jaboatão dos Guararapes' => 'PE', 'Paulista' => 'PE',
        'Juazeiro do Norte' => 'CE', 'Caucaia' => 'CE', 'Sobral' => 'CE', 'Campina Grande' => 'PB', 'Mossoró' => 'RN', 'Parnamirim' => 'RN', 'Imperatriz' => 'MA', 'Arapiraca' => 'AL',
        'Santarém' => 'PA', 'Marabá' => 'PA', 'Ananindeua' => 'PA', 'Parauapebas' => 'PA',
        'São Vicente' => 'SP', 'Guarujá' => 'SP', 'Praia Grande' => 'SP', 'Taubaté' => 'SP', 'Limeira' => 'SP', 'Suzano' => 'SP', 'Taboão da Serra' => 'SP', 'Sumaré' => 'SP', 'Carapicuíba' => 'SP', 'Diadema' => 'SP',
        'Mogi das Cruzes' => 'SP', 'Indaiatuba' => 'SP', 'Cotia' => 'SP', 'Itaquaquecetuba' => 'SP', 'Americana' => 'SP', 'Marília' => 'SP', 'Presidente Prudente' => 'SP', 'Araraquara' => 'SP', 'São Carlos' => 'SP', 'Jacareí' => 'SP',
        'Hortolândia' => 'SP', 'Rio Claro' => 'SP', 'Araçatuba' => 'SP', 'Itapevi' => 'SP', 'Barretos' => 'SP', 'Botucatu' => 'SP', 'Atibaia' => 'SP', 'Bragança Paulista' => 'SP', 'Valinhos' => 'SP', 'Cubatão' => 'SP', 'Pindamonhangaba' => 'SP',
        'Guaratinguetá' => 'SP', 'São Caetano do Sul' => 'SP', 'Belford Roxo' => 'RJ', 'São João de Meriti' => 'RJ', 'Itaboraí' => 'RJ', 'Cabo Frio' => 'RJ', 'Angra dos Reis' => 'RJ', 'Nova Friburgo' => 'RJ', 'Teresópolis' => 'RJ',
        'Barra Mansa' => 'RJ', 'Resende' => 'RJ', 'Rio das Ostras' => 'RJ', 'Maricá' => 'RJ', 'Divinópolis' => 'MG', 'Poços de Caldas' => 'MG', 'Sete Lagoas' => 'MG', 'Ribeirão das Neves' => 'MG', 'Varginha' => 'MG',
        'Pouso Alegre' => 'MG', 'Patos de Minas' => 'MG', 'Teófilo Otoni' => 'MG', 'Lavras' => 'MG', 'Itabira' => 'MG', 'Barbacena' => 'MG', 'Colatina' => 'ES', 'Guarapari' => 'ES', 'Colombo' => 'PR', 'Guarapuava' => 'PR',
        'Paranaguá' => 'PR', 'Apucarana' => 'PR', 'Toledo' => 'PR', 'Araucária' => 'PR', 'Pinhais' => 'PR', 'Campo Largo' => 'PR', 'Umuarama' => 'PR', 'Palhoça' => 'SC', 'Lages' => 'SC', 'Jaraguá do Sul' => 'SC', 'Brusque' => 'SC', 'Tubarão' => 'SC',
        'Passo Fundo' => 'RS', 'São Leopoldo' => 'RS', 'Alvorada' => 'RS', 'Viamão' => 'RS', 'Santa Cruz do Sul' => 'RS', 'Cachoeirinha' => 'RS', 'Bagé' => 'RS', 'Uruguaiana' => 'RS', 'Bento Gonçalves' => 'RS',
        'Lauro de Freitas' => 'BA', 'Itabuna' => 'BA', 'Jequié' => 'BA', 'Alagoinhas' => 'BA', 'Barreiras' => 'BA', 'Teixeira de Freitas' => 'BA', 'Porto Seguro' => 'BA', 'Paulo Afonso' => 'BA', 'Eunápolis' => 'BA',
        'Cabo de Santo Agostinho' => 'PE', 'Camaragibe' => 'PE', 'Garanhuns' => 'PE', 'Santa Cruz do Capibaribe' => 'PE', 'Abreu e Lima' => 'PE', 'Maracanaú' => 'CE', 'Itapipoca' => 'CE', 'Maranguape' => 'CE', 'Iguatu' => 'CE', 'Crato' => 'CE',
        'Bayeux' => 'PB', 'Cabedelo' => 'PB', 'Caicó' => 'RN', 'Macaíba' => 'RN', 'Lagarto' => 'SE', 'Itabaiana' => 'SE', 'São José de Ribamar' => 'MA', 'Timon' => 'MA', 'Açailândia' => 'MA', 'Parnaíba' => 'PI', 'Picos' => 'PI',
        'Castanhal' => 'PA', 'Abaetetuba' => 'PA', 'Itaituba' => 'PA', 'Parintins' => 'AM', 'Itacoatiara' => 'AM', 'Ji-Paraná' => 'RO', 'Ariquemes' => 'RO', 'Vilhena' => 'RO', 'Cacoal' => 'RO', 'Cruzeiro do Sul' => 'AC', 'Santana' => 'AP',
        'Araguaína' => 'TO', 'Gurupi' => 'TO', 'Luziânia' => 'GO', 'Valparaíso de Goiás' => 'GO', 'Águas Lindas de Goiás' => 'GO', 'Trindade' => 'GO', 'Senador Canedo' => 'GO', 'Catalão' => 'GO', 'Itumbiara' => 'GO', 'Jataí' => 'GO',
        'Tangará da Serra' => 'MT', 'Cáceres' => 'MT', 'Sorriso' => 'MT', 'Lucas do Rio Verde' => 'MT', 'Primavera do Leste' => 'MT', 'Três Lagoas' => 'MS', 'Corumbá' => 'MS', 'Ponta Porã' => 'MS', 'Naviraí' => 'MS',
        'Ceilândia' => 'DF', 'Taguatinga' => 'DF', 'Samambaia' => 'DF',
        'Anápolis' => 'GO', 'Aparecida de Goiânia' => 'GO', 'Rio Verde' => 'GO', 'Dourados' => 'MS', 'Várzea Grande' => 'MT', 'Rondonópolis' => 'MT', 'Sinop' => 'MT',
    ];
}

/** Names that are also ordinary words or other things: they only count when the text also names the state. @return array<int,string> */
function radar_job_ambiguous_cities(): array
{
    return ['Natal', 'Vitória', 'Palmas', 'Santos', 'Serra', 'Paulista', 'Franca', 'Juazeiro', 'Belém', 'Toledo', 'Santana', 'Trindade', 'Americana', 'Sorriso', 'Alvorada', 'Resende', 'Itabira', 'Lavras', 'Crato', 'Picos', 'Timon', 'Cacoal', 'Gurupi'];
}

/** @return array<string,string> key => label of the age groups a story can announce */
function radar_job_ages(): array
{
    return [
        'aprendiz' => 'Jovem aprendiz (14 a 24 anos)',
        'primeiro_emprego' => 'Primeiro emprego',
        'estagio' => 'Estágio (estudantes)',
        '50mais' => '50+ (maiores de 45 ou 50 anos)',
    ];
}

/** Words that, with "vaga(s)", say the story is about jobs (a "vaga" alone can be a final, a parking spot or a seat). @return array<int,string> */
function radar_job_context_words(): array
{
    return ['empleo', 'empleos', 'laboral', 'laborales', 'puestos', 'postulacion', 'postulaciones', 'postular', 'curriculum', 'feria de empleo', 'emprego', 'empregos', 'trabalho', 'estagio', 'estagios', 'aprendiz', 'clt', 'contratacao', 'contratacoes', 'contratando', 'salario', 'salarios', 'carteira assinada', 'candidatos', 'curriculo', 'sine', 'concurso', 'edital', 'inscricoes abertas', 'processo seletivo'];
}

/** Phrases that say it on their own. @return array<int,string> */
function radar_job_strong_phrases(): array
{
    return [
        'vagas de emprego', 'vaga de emprego', 'vagas de trabalho', 'vaga de trabalho', 'vagas de estagio', 'vaga de estagio', 'vagas de aprendiz', 'vagas abertas', 'oferta de emprego', 'ofertas de emprego',
        'oportunidades de emprego', 'oportunidade de emprego', 'mutirao de emprego', 'feira de emprego', 'feira de empregos', 'jovem aprendiz', 'jovens aprendizes', 'primeiro emprego', 'processo seletivo',
        'vagas temporarias', 'vagas com carteira assinada', 'vagas para pcd', 'vagas pcd', 'vagas de emprego',
        // Spanish (Paraguay)
        'ofertas de empleo', 'oferta de empleo', 'oferta laboral', 'ofertas laborales', 'puestos de trabajo', 'puestos laborales', 'bolsa de trabajo', 'feria de empleo', 'ferias de empleo', 'expo empleo',
        'convocatoria laboral', 'oportunidades laborales', 'oportunidad laboral', 'primer empleo',
    ];
}

/**
 * Public feeds of Brazilian job sites and of the vacancy sections of news portals, read like any other source of the panel.
 * "listing": a vacancy board, every post is one opening (the state is where the board works). "tag": a news portal's
 * vacancy section, where only the posts that offer jobs count. Every address was probed live and answered with a feed.
 *
 * @return array<int,array{name:string,url:string,kind:string,state:string}>
 */
function radar_job_direct_feeds(): array
{
    return [
        ['name' => 'Empregos MG', 'url' => 'https://www.empregosmg.com.br/feed/', 'kind' => 'listing', 'state' => 'MG'],
        ['name' => 'Vagas de Emprego SP', 'url' => 'https://www.vagasdeempregosp.com.br/feed/', 'kind' => 'listing', 'state' => 'SP'],
        ['name' => 'Vagas PE', 'url' => 'https://www.vagaspe.com.br/feed/', 'kind' => 'listing', 'state' => 'PE'],
        ['name' => 'Empregos PE', 'url' => 'https://www.empregope.com.br/feed/', 'kind' => 'listing', 'state' => 'PE'],
        ['name' => 'Empregos Recife', 'url' => 'https://www.empregosrecife.com.br/feed/', 'kind' => 'listing', 'state' => 'PE'],
        ['name' => 'Empregos ES', 'url' => 'https://www.empregoses.com.br/feed/', 'kind' => 'listing', 'state' => 'ES'],
        ['name' => 'Vagas Floripa (SC)', 'url' => 'https://www.empregossc.com.br/feed/', 'kind' => 'listing', 'state' => 'SC'],
        ['name' => 'Emprego em Salvador', 'url' => 'https://www.empregoemsalvador.com.br/feed/', 'kind' => 'listing', 'state' => 'BA'],
        ['name' => 'Empregos Brasília', 'url' => 'https://www.empregosbrasilia.com.br/feed/', 'kind' => 'listing', 'state' => 'DF'],
        ['name' => 'Vagas Curitiba', 'url' => 'https://www.vagascuritiba.com.br/feed/', 'kind' => 'listing', 'state' => 'PR'],
        ['name' => 'g1 · Concursos e Emprego', 'url' => 'https://g1.globo.com/rss/g1/concursos-e-emprego/', 'kind' => 'tag', 'state' => ''],
        ['name' => 'Jornal de Brasília · Vagas de emprego', 'url' => 'https://www.jornaldebrasilia.com.br/tag/vagas-de-emprego/feed/', 'kind' => 'tag', 'state' => 'DF'],
        ['name' => 'Metrópoles · Vagas de emprego', 'url' => 'https://www.metropoles.com/tag/vagas-de-emprego/feed/', 'kind' => 'tag', 'state' => 'DF'],
        ['name' => 'Portal de Prefeitura · Vagas de emprego', 'url' => 'https://www.portaldeprefeitura.com.br/tag/vagas-de-emprego/feed/', 'kind' => 'tag', 'state' => 'PE'],
        ['name' => 'ac24horas · Vagas', 'url' => 'https://www.ac24horas.com/tag/vagas/feed/', 'kind' => 'tag', 'state' => 'AC'],
        ['name' => 'Jornal Contábil · Vagas de emprego', 'url' => 'https://www.jornalcontabil.com.br/tag/vagas-de-emprego/feed/', 'kind' => 'tag', 'state' => ''],
        ['name' => 'BPMoney · Vagas', 'url' => 'https://www.bpmoney.com.br/tag/vagas/feed/', 'kind' => 'tag', 'state' => ''],
        ['name' => 'Jovem Aprendiz (jovemaprendiz.org)', 'url' => 'https://www.jovemaprendiz.org/feed/', 'kind' => 'tag', 'state' => ''],
        ['name' => 'Concursos no Brasil', 'url' => 'https://www.concursosnobrasil.com.br/feed/', 'kind' => 'tag', 'state' => ''],
        ['name' => 'Concursos RN', 'url' => 'https://www.concursosrn.com.br/feed/', 'kind' => 'tag', 'state' => 'RN'],
        ['name' => 'Concursos SC', 'url' => 'https://www.concursossc.com.br/feed/', 'kind' => 'tag', 'state' => 'SC'],
        ['name' => 'Diario HOY · Empleo (Paraguay)', 'url' => 'https://www.hoy.com.py/tag/empleo/feed', 'kind' => 'tag', 'state' => 'PY'],
    ];
}

/** What the panel knows about a source address that is one of the direct vacancy feeds, or null. @return array{name:string,url:string,kind:string,state:string}|null */
function radar_job_feed_info(string $url): ?array
{
    if ($url === '') {
        return null;
    }
    foreach (radar_job_direct_feeds() as $feed) {
        if ($feed['url'] === $url) {
            return $feed;
        }
    }
    if (str_starts_with($url, 'https://news.google.com/rss/search') && str_contains($url, 'gl=PY')) {
        return ['name' => 'Google Notícias · Paraguai', 'url' => $url, 'kind' => 'tag', 'state' => 'PY'];
    }
    return null;
}

/** Posts of a vacancy board that are advice and not an opening ("Como escrever uma carta", "Erros na entrevista"). */
function radar_job_looks_like_advice(string $title): bool
{
    $folded = ' ' . radar_fold($title) . ' ';
    return preg_match('/^ (como|dicas?|saiba|o que|por que|porque|erros?|guia|veja como|passo a passo|confira dicas|\d+ dicas|taxa selic|salario minimo|bolsa familia) /', $folded) === 1
        || preg_match('/ (curriculo|entrevista de emprego|carta de apresentacao|dicas de|linkedin) /', $folded) === 1 && !str_contains($folded, ' vaga ');
}

/** The broader test for a source that is already about jobs: verbs of hiring, besides the phrases of radar_job_is_vacancy(). */
function radar_job_is_listing(string $title, string $summary = ''): bool
{
    if (radar_job_is_vacancy($title, $summary)) {
        return true;
    }
    $text = ' ' . radar_fold($title . ' ' . $summary) . ' ';
    return preg_match('/ (contrata|contratam|contratando|selecionam?|recruta|recrutam|admite|abre selecao|abre processo|abre oportunidades?|oferece oportunidades?|oferecem oportunidades?|vagas? para|precisa de|esta contratando|estao contratando|contratan|busca personal|solicita personal|se buscan|ofrece(n)? (vacantes|puestos|empleos?|oportunidades)|abre(n)? convocatoria) /', $text) === 1;
}

/** True when the headline or the summary announces job openings. */
function radar_job_is_vacancy(string $title, string $summary = ''): bool
{
    $text = ' ' . radar_fold($title . ' ' . $summary) . ' ';
    foreach (radar_job_strong_phrases() as $phrase) {
        if (str_contains($text, ' ' . $phrase . ' ')) {
            return true;
        }
    }
    // "Ofrecen 200 puestos", "ofrece vacantes": the offer itself, in Spanish.
    if (preg_match('/ ofrece(n)? (mas de )?([0-9. ]+ )?(puestos|vacantes|empleos|plazas) /', $text) === 1) {
        return true;
    }
    if (preg_match('/ (vagas?|vacantes?|vacancias?) /', $text) !== 1) {
        return false;
    }
    foreach (radar_job_context_words() as $word) {
        if (str_contains($text, ' ' . $word . ' ')) {
            return true;
        }
    }
    return false;
}

/**
 * Region, state, city and age group named by a vacancy story. Each one is '' when the text does not say.
 *
 * @return array{region:string,state:string,city:string,age:string}
 */
function radar_job_extract(string $title, string $summary = '', string $defaultState = ''): array
{
    $states = radar_job_states();
    $original = $title . ' ' . $summary;
    $foldedTitle = ' ' . radar_fold($title) . ' ';
    $foldedAll = ' ' . radar_fold($title . ' ' . $summary) . ' ';
    if ($defaultState === 'PY' || radar_job_is_paraguay($foldedAll)) {
        return radar_job_extract_py($foldedTitle, $foldedAll, $original);
    }
    // State: its name, or its abbreviation written as "(SP)", "- SP", "/SP", ", SP" or "em SP".
    $state = '';
    $names = [];
    foreach ($states as $uf => $info) {
        // "Pará" folds to the word "para": it only counts when written with its accent and a capital letter (checked below).
        if ($uf !== 'PA') {
            $names[radar_fold($info['name'])] = $uf;
        }
    }
    uksort($names, static fn(string $a, string $b): int => strlen($b) <=> strlen($a));
    foreach ([$foldedTitle, $foldedAll] as $haystack) {
        foreach ($names as $name => $uf) {
            if (str_contains($haystack, ' ' . $name . ' ')) {
                $state = $uf;
                break 2;
            }
        }
    }
    if ($state === '' && preg_match('/(?<![\p{L}])(?:Pará|PARÁ)(?![\p{L}])/u', $original) === 1) {
        $state = 'PA';
    }
    if ($state === '' && preg_match('/(?:[(\-–\/,]|\b(?:em|de|do|da|no|na)\b)\s*(' . implode('|', array_keys($states)) . ')\b/u', $original, $match) === 1) {
        $state = $match[1];
    }
    // A vacancy board of one state (Empregos MG, Vagas PE...) says where the opening is when the text does not.
    if ($state === '' && isset($states[$defaultState])) {
        $state = $defaultState;
    }
    // City: the longest name first; the title wins over the summary; a homonym of another state is not taken.
    $cities = radar_job_cities();
    uksort($cities, static fn(string $a, string $b): int => strlen(radar_fold($b)) <=> strlen(radar_fold($a)));
    $city = '';
    foreach ([$foldedTitle, $foldedAll] as $haystack) {
        foreach ($cities as $name => $uf) {
            if (!str_contains($haystack, ' ' . radar_fold($name) . ' ')) {
                continue;
            }
            if (($state !== '' && $state !== $uf) || (in_array($name, radar_job_ambiguous_cities(), true) && $state !== $uf)) {
                continue;
            }
            $city = $name;
            $state = $state !== '' ? $state : $uf;
            break 2;
        }
    }
    $region = $state !== '' ? $states[$state]['region'] : '';
    if ($region === '') {
        foreach (['nordeste' => 'Nordeste', 'sudeste' => 'Sudeste', 'centro oeste' => 'Centro-Oeste', 'regiao norte' => 'Norte', 'regiao sul' => 'Sul', 'no norte do pais' => 'Norte', 'no sul do pais' => 'Sul'] as $word => $name) {
            if (str_contains($foldedAll, ' ' . $word . ' ')) {
                $region = $name;
                break;
            }
        }
    }
    $age = radar_job_age($foldedAll, $original);
    return ['region' => $region, 'state' => $state, 'city' => $city, 'age' => $age];
}

/** The age group a text announces (Portuguese or Spanish), or ''. */
function radar_job_age(string $foldedAll, string $original): string
{
    if (preg_match('/ (jovem aprendiz|jovens aprendizes|menor aprendiz|vagas? de aprendiz|programa aprendiz) /', $foldedAll) === 1) {
        return 'aprendiz';
    }
    if (preg_match('/ (primeiro emprego|primer empleo) /', $foldedAll) === 1) {
        return 'primeiro_emprego';
    }
    if (preg_match('/ (50 mais|45 mais|60 mais|maiores de (45|50|55|60)|acima de (45|50|55|60)|(45|50|55|60) anos ou mais|a partir de (45|50|55|60) anos|idosos|terceira idade|melhor idade|profissionais maduros|mayores de (45|50|55|60)|adultos mayores|tercera edad|(45|50|55|60) anos o mas) /', $foldedAll) === 1
        || preg_match('/\b(45|50|60)\s*\+/u', $original) === 1) {
        return '50mais';
    }
    if (preg_match('/ (estagio|estagios|estagiario|estagiarios|estagiaria|pasantia|pasantias|pasante|pasantes) /', $foldedAll) === 1) {
        return 'estagio';
    }
    return '';
}

/**
 * Place of a vacancy story about Paraguay: the region is always "Paraguai"; the city and the department come from the
 * names written in the text (a city gives its department).
 *
 * @return array{region:string,state:string,city:string,age:string}
 */
function radar_job_extract_py(string $foldedTitle, string $foldedAll, string $original): array
{
    $cities = radar_job_py_cities();
    uksort($cities, static fn(string $a, string $b): int => strlen(radar_fold($b)) <=> strlen(radar_fold($a)));
    $city = '';
    $state = '';
    foreach ([$foldedTitle, $foldedAll] as $haystack) {
        foreach ($cities as $name => $code) {
            if (str_contains($haystack, ' ' . radar_fold($name) . ' ')) {
                [$city, $state] = [$name, $code];
                break 2;
            }
        }
    }
    if ($state === '') {
        // Departments that are not also an everyday word ("Central" and "Misiones" are only read in a city or after "departamento").
        foreach (['PY-10' => 'alto parana', 'PY-16' => 'alto paraguay', 'PY-13' => 'amambay', 'PY-19' => 'boqueron', 'PY-5' => 'caaguazu', 'PY-6' => 'caazapa', 'PY-14' => 'canindeyu', 'PY-4' => 'guaira', 'PY-7' => 'itapua',
            'PY-12' => 'neembucu', 'PY-9' => 'paraguari', 'PY-15' => 'presidente hayes', 'PY-3' => 'cordillera', 'PY-11' => 'departamento central', 'PY-ASU' => 'gran asuncion'] as $code => $name) {
            if (str_contains($foldedAll, ' ' . $name . ' ')) {
                $state = $code;
                break;
            }
        }
    }
    return ['region' => 'Paraguai', 'state' => $state, 'city' => $city, 'age' => radar_job_age($foldedAll, $original)];
}

/** Read the place and the age of a stored story and save them (only for vacancy stories). */
function radar_job_store(int $newsId, string $title, string $summary, string $sourceUrl = ''): void
{
    $job = radar_job_extract($title, $summary, (string)(radar_job_feed_info($sourceUrl)['state'] ?? ''));
    radar_db_exec('UPDATE news SET job_region = ?, job_state = ?, job_city = ?, job_age = ? WHERE id = ?', [$job['region'], $job['state'], $job['city'], $job['age'], $newsId]);
}

/** Fill the place and the age of the vacancy stories that do not have them yet (after a re-classification). */
function radar_job_backfill(): int
{
    $rows = radar_db_all("SELECT n.id, n.title, n.summary, s.url AS source_url FROM news n LEFT JOIN sources s ON s.id = n.source_id WHERE n.category = ? AND n.job_region = '' AND n.job_state = '' AND n.job_city = '' AND n.job_age = ''", [RADAR_JOB_CATEGORY]);
    foreach ($rows as $row) {
        radar_job_store((int)$row['id'], (string)$row['title'], (string)$row['summary'], (string)($row['source_url'] ?? ''));
    }
    return count($rows);
}

/**
 * Searches that bring the vacancy announcements: general, by age group, by region and by state. They are Google News
 * searches (the publishers' own articles); job boards do not offer feeds.
 *
 * @return array<int,array{name:string,url:string,category:string,region:string,weight:float}>
 */
function radar_job_sources(): array
{
    $g = 'hl=pt-BR&gl=BR&ceid=BR:pt-419';
    $feed = static fn(string $name, string $query, float $weight): array => [
        'name' => 'Google Notícias · Vagas · ' . $name, 'url' => 'https://news.google.com/rss/search?q=' . str_replace('%20', '+', rawurlencode($query)) . '&' . $g,
        'category' => RADAR_JOB_CATEGORY, 'region' => 'BR', 'weight' => $weight,
    ];
    $sources = [];
    foreach (radar_job_direct_feeds() as $direct) {
        $sources[] = ['name' => $direct['name'], 'url' => $direct['url'], 'category' => RADAR_JOB_CATEGORY, 'region' => 'BR', 'weight' => $direct['kind'] === 'listing' ? 1.0 : 0.9];
    }
    $sources = array_merge($sources, [
        $feed('Brasil', '"vagas de emprego"', 0.9),
        $feed('Brasil (últimas 24 h)', '"vagas de emprego" when:1d', 0.9),
        $feed('Jovem aprendiz', '"jovem aprendiz" vagas', 0.8),
        $feed('Primeiro emprego', '"primeiro emprego" vagas', 0.8),
        $feed('Estágio', 'vagas de estágio', 0.8),
        $feed('50+ e maiores de 50', 'vagas emprego "50+" OR "maiores de 50" OR "60+" OR "terceira idade"', 0.8),
        $feed('Mutirões e processos seletivos', 'mutirão de emprego OR "feira de empregos" OR "processo seletivo" vagas', 0.8),
    ]);
    foreach (radar_job_paraguay_feeds() as $py) {
        $sources[] = $py;
    }
    foreach (radar_job_brazil_regions() as $region) {
        $sources[] = $feed('Região ' . $region, '"vagas de emprego" região ' . $region . ' when:7d', 0.8);
    }
    foreach (radar_job_states() as $info) {
        $sources[] = $feed($info['name'], '"vagas de emprego" ' . $info['name'] . ' when:7d', 0.8);
    }
    return $sources;
}

/**
 * Google News searches of Paraguay (Spanish, edition of Paraguay) and of Brazilians looking for work there (Portuguese).
 *
 * @return array<int,array{name:string,url:string,category:string,region:string,weight:float}>
 */
function radar_job_paraguay_feeds(): array
{
    $spanish = 'hl=es-419&gl=PY&ceid=PY:es-419';
    $portuguese = 'hl=pt-BR&gl=BR&ceid=BR:pt-419';
    $feed = static fn(string $name, string $query, string $edition): array => [
        'name' => 'Google Notícias · Vagas · Paraguai · ' . $name, 'url' => 'https://news.google.com/rss/search?q=' . str_replace('%20', '+', rawurlencode($query)) . '&' . $edition,
        'category' => RADAR_JOB_CATEGORY, 'region' => 'INT', 'weight' => 0.8,
    ];
    return [
        $feed('Ofertas de empleo', '"ofertas de empleo" Paraguay', $spanish),
        $feed('Vacantes', 'empleo vacantes Paraguay', $spanish),
        $feed('Primer empleo', '"primer empleo" Paraguay', $spanish),
        $feed('Pasantías', 'pasantías Paraguay', $spanish),
        $feed('Ferias de empleo', 'feria de empleo Paraguay', $spanish),
        $feed('Asunción', 'empleo Asunción vacantes', $spanish),
        $feed('Ciudad del Este', '"Ciudad del Este" empleo vacantes', $spanish),
        $feed('Encarnación', 'empleo Encarnación vacantes', $spanish),
        $feed('Para brasileiros', 'vagas de emprego Paraguai', $portuguese),
        $feed('Ciudad del Este (PT)', 'vagas emprego "Ciudad del Este"', $portuguese),
    ];
}

/**
 * What the filters of the vacancy stories can offer: the regions, states, cities and age groups that exist right now, with how many stories each has.
 *
 * @return array{regions:array<string,int>,states:array<string,int>,cities:array<int,array{city:string,state:string,total:int}>,ages:array<string,int>,total:int}
 */
function radar_job_facets(int $since, string $state = ''): array
{
    $effective = 'COALESCE(published_at, first_seen_at) >= ?';
    $base = 'category = ? AND ' . $effective;
    $params = [RADAR_JOB_CATEGORY, $since];
    $count = static function (string $column) use ($base, $params): array {
        $out = [];
        foreach (radar_db_all("SELECT $column AS value, COUNT(*) AS total FROM news WHERE $base AND $column != '' GROUP BY $column ORDER BY total DESC", $params) as $row) {
            $out[(string)$row['value']] = (int)$row['total'];
        }
        return $out;
    };
    $cityParams = $params;
    $cityWhere = $base . " AND job_city != ''";
    if ($state !== '') {
        $cityWhere .= ' AND job_state = ?';
        $cityParams[] = $state;
    }
    $cities = [];
    foreach (radar_db_all("SELECT job_city, job_state, COUNT(*) AS total FROM news WHERE $cityWhere GROUP BY job_city, job_state ORDER BY total DESC LIMIT 120", $cityParams) as $row) {
        $cities[] = ['city' => (string)$row['job_city'], 'state' => (string)$row['job_state'], 'total' => (int)$row['total']];
    }
    usort($cities, static fn(array $a, array $b): int => strcmp(radar_fold($a['city']), radar_fold($b['city'])));
    return ['regions' => $count('job_region'), 'states' => $count('job_state'), 'cities' => $cities, 'ages' => $count('job_age'), 'total' => (int)radar_db_value("SELECT COUNT(*) FROM news WHERE $base", $params)];
}
