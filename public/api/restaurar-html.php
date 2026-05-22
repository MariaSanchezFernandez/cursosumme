<?php
// ─────────────────────────────────────────────────────────────
// api/restaurar-html.php  —  Restaura descripciones HTML de cursos y temas
// Uso: GET /api/restaurar-html.php?key=SETUP_KEY
// Escribe el HTML canónico de cada curso/tema directamente en BD.
// ─────────────────────────────────────────────────────────────

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db-connect.php';

// ── Protección: requiere ?key=SETUP_KEY ──────────────────────
$keyProvided = trim($_GET['key'] ?? '');
if (!defined('SETUP_KEY') || !hash_equals(SETUP_KEY, $keyProvided)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'mensaje' => 'Acceso denegado. Usa ?key=TU_CLAVE']);
    exit;
}

$pdo = obtenerPDO();
$actualizados = 0;

// ── Helper ────────────────────────────────────────────────────
function up(PDO $pdo, string $tabla, int $id, string $html): void {
    global $actualizados;
    $stmt = $pdo->prepare("UPDATE {$tabla} SET descripcion = :d WHERE id = :id");
    $stmt->execute([':d' => $html, ':id' => $id]);
    $actualizados++;
}

// ══════════════════════════════════════════════════════════════
// CURSOS
// ══════════════════════════════════════════════════════════════

// cursos id=7 y id=12 comparten la misma descripción
$htmlCursoProgramacion = <<<'END'
<p>¡Hola! Me alegra muchísimo que estés aquí.</p>
<p>Si has llegado a este curso es porque quieres que tu programación cuente de verdad. Que cuando el tribunal la tenga en sus manos, sienta que detrás de ese documento hay una maestra PT con criterio, con visión y con ganas de hacer las cosas bien.</p>
<p>Eso es exactamente lo que vamos a construir juntas.</p>
<p>Este no es un curso para rellenar apartados. Es un curso para tomar decisiones pedagógicas conscientes — saber qué poner, por qué ponerlo y cómo decirlo de una manera que sume puntos y te represente.</p>
<p>Vamos a trabajar paso a paso, con calma y con criterio. Cada bloque tiene su vídeo, sus ejemplos y su checklist para que puedas avanzar sin perderte nada importante.</p>
<p>Mi consejo antes de empezar: no vayas con prisa. Lee cada apartado, déjalo reposar y vuelve a revisarlo al día siguiente. Lo que parece perfecto recién escrito siempre mejora con perspectiva.</p>
<p>Tienes todo lo que necesitas para hacer una programación de 10. Ahora vamos a demostrarlo.</p>
<p>¡Empezamos! 🌿</p>
END;

up($pdo, 'cursos', 7, $htmlCursoProgramacion);
up($pdo, 'cursos', 12, $htmlCursoProgramacion);

// ══════════════════════════════════════════════════════════════
// TEMAS
// ══════════════════════════════════════════════════════════════

// ── temas id=6 — BLOQUE 1. CONOCIMIENTOS PREVIOS (curso 6) ───
$htmlTema6 = <<<'END'
<h2>✨ Bienvenidos al Bloque 1: Conocimientos previos</h2>
<p>¿Crees que aprobar la oposición es cuestión de quién sabe más? Error.</p>
<p>En este bloque vamos a romper los mitos del estudio tradicional para convertirte en un auténtico estratega del examen. No solo vas a aprender contenido con sentido; vas a aprender a construir un "Tema de 10" que deje al tribunal sin palabras.</p>
<h3>Lo que trabajamos en este bloque</h3>
<ul>
<li><strong>Vídeo 1 · ¿Qué evalúa realmente el tribunal?</strong> No basta con escribir… hay que intervenir con intención. En esta sesión, Rocío te enseña a alinear tu escritura con lo que el tribunal realmente busca.</li>
<li><strong>Vídeo 2 · La arquitectura invisible de un tema excelente</strong> El título te dice qué decir… pero no cómo decirlo. En este vídeo pasamos de la teoría a la práctica: estructura visual, lectura fácil y organización coherente para que el tribunal disfrute leyéndote.</li>
<li><strong>Vídeo 3 · La importancia del mapa mental previo</strong> ¿Miedo a la hoja en blanco? Nunca más. Aprenderás a crear una hoja de ruta mental que reduzca tu carga cognitiva y te dé seguridad total.</li>
</ul>
<p><em>"Tu éxito no está en estudiar 25 temas; está en tu constancia, rigor y firmeza de quien cree en su propio trabajo."</em></p>
END;

up($pdo, 'temas', 6, $htmlTema6);

// ── temas id=12 y id=44 — BLOQUE 1. PASOS PREVIOS ────────────
$htmlTema12y44 = <<<'END'
<h2>🧭 Bloque 1. Pasos previos</h2>
<p>Antes de escribir una sola línea de tu programación, hay decisiones que lo cambian todo.</p>
<p>Este bloque es el punto de partida real. Aquí no empezamos por el índice ni por rellenar apartados — empezamos por entender qué estás construyendo, para quién y con qué identidad.</p>
<p>Porque una programación sin criterio propio es solo un documento. Y tú vas a hacer algo mucho más que eso.</p>
<h3>Lo que trabajamos en este bloque</h3>
<ul>
<li><strong>🔍 Vídeo 1 · ¿Qué evalúa realmente el tribunal?</strong> Analizamos la convocatoria y la rúbrica con lupa. Sabrás exactamente qué busca el tribunal en la Parte B, qué marca la diferencia entre una programación que aprueba y una que destaca, y qué errores debes evitar desde el primer momento.</li>
<li><strong>👩‍🏫 Vídeo 2 · ¿Cuál es tu identidad docente?</strong> Uno de los apartados más potentes del curso. Descubrirás qué es la identidad docente, por qué el tribunal la valora y cómo construir la tuya de forma auténtica — tanto si te decantas por aula ordinaria como por aula específica. Incluye documento descargable: Identidad docente.</li>
<li><strong>🧵 Vídeo 3 · Busca tu hilo conductor</strong> El error más frecuente en las programaciones es no tenerlo. Aprenderás qué es un hilo conductor, qué características debe tener, cómo elegirlo y cómo integrarlo con coherencia de principio a fin. Con ejemplos reales de compañeras que ya lo han aplicado. Incluye documento descargable: Hilo conductor.</li>
<li><strong>✨ Vídeo 4 · La estética siempre suma</strong> El diseño visual no es un capricho — es un mensaje. Veremos por qué la presentación importa, cómo respetar las indicaciones de la convocatoria y cómo dar a tu programación una estética profesional paso a paso, con trucos reales en Canva y Word.</li>
<li><strong>🏗️ Vídeo 5 · La estructura perfecta: el esqueleto</strong> Aquí construimos el andamiaje de toda tu programación. Qué es realmente un Plan de Apoyo, qué quiere ver el tribunal, cómo diseñar un índice estratégico y por qué la coherencia interna lo es todo. Incluye documento descargable: Índice (inclusión / específica).</li>
</ul>
<h3>📌 Al terminar este bloque sabrás</h3>
<ul>
<li>Qué criterios usa el tribunal para puntuarte</li>
<li>Quién eres como maestra PT y cómo transmitirlo</li>
<li>Cuál es tu hilo conductor y cómo usarlo</li>
<li>Qué estructura va a tener tu programación antes de escribir una sola palabra</li>
</ul>
END;

up($pdo, 'temas', 12, $htmlTema12y44);
up($pdo, 'temas', 44, $htmlTema12y44);

// ── temas id=13 y id=45 — BLOQUE 2. COMIENZA TU PA ───────────
$htmlTema13y45 = <<<'END'
<p>El momento de empezar siempre da vértigo. Este bloque está diseñado para que des ese primer paso con seguridad y con criterio.</p>
<p>Aquí arranca tu Plan de Apoyo de verdad. Vídeo a vídeo vas a construir los primeros apartados con intención pedagógica real — no copiando estructuras de otros, sino tomando decisiones propias que el tribunal va a notar.</p>
<p>Recuerda: cada vez que termines un apartado, déjalo reposar. Vuelve al día siguiente con ojos nuevos. Siempre mejora.</p>
<h3>Lo que trabajamos en este bloque</h3>
<ul>
<li><strong>🗂️ Vídeo 6 · Nivel -1: qué es una programación</strong> Antes de construir, necesitas tener claro qué estás haciendo y por qué. Revisamos qué es una programación, cómo se relaciona con las Situaciones de Aprendizaje, qué dice la convocatoria y los criterios de evaluación, y cómo elegir el nivel educativo que mejor te conviene defender.</li>
<li><strong>🚪 Vídeo 7 · Nivel 1: la portada</strong> La portada es la puerta de entrada a tu programación — y las primeras impresiones cuentan. Aprenderás a diseñarla con criterio, a contar una historia desde la primera página y a revisar cada detalle antes de darla por cerrada. Con ejemplos reales y checklist descargable: Portada.</li>
<li><strong>✍️ Vídeo 8 · Introduce y justifica tu PA</strong> La introducción y la justificación son los apartados donde muchas opositoras pierden puntos sin darse cuenta. Aquí aprenderás a estructurarlos con cabeza: cómo repartir los párrafos, cómo usar la legislación como aliada sin abusar de ella, qué elementos no pueden faltar y cómo conectar ambos apartados con coherencia. Con rúbrica real y checklist descargable: Introducción · Justificación.</li>
</ul>
<h3>📌 Al terminar este bloque tendrás</h3>
<ul>
<li>El nivel educativo elegido y justificado</li>
<li>Una portada que abre con personalidad</li>
<li>Una introducción y justificación sólidas, bien estructuradas y alineadas con los criterios del tribunal</li>
</ul>
END;

up($pdo, 'temas', 13, $htmlTema13y45);
up($pdo, 'temas', 45, $htmlTema13y45);

// ── temas id=14 y id=46 — BLOQUE 3. SITÚA TU INTERVENCIÓN ────
$htmlTema14y46 = <<<'END'
<p>El tribunal necesita saber dónde estás, con quién trabajas y en qué contexto interviene tu labor como maestra PT. Este bloque te enseña a contarlo con precisión y con sentido.</p>
<p>La contextualización no es un trámite burocrático. Es la oportunidad de demostrar que conoces tu entorno, que has reflexionado sobre él y que tu intervención tiene un anclaje real. Una contextualización bien construida da credibilidad a todo lo que viene después.</p>
<p>Aquí vas a crear tu historia. No inventarla — construirla con criterio.</p>
<h3>Lo que trabajamos en este bloque</h3>
<ul>
<li><strong>🏘️ Vídeo 9 · Contextualiza tu PA: la localidad</strong> Aprenderás a elegir y describir el contexto de localidad con los datos que realmente suman. Veremos cómo estructurar los párrafos, qué información es relevante para el tribunal y cómo construir una contextualización que no suene a copia-pega. Con rúbrica real y checklist descargable: Contexto · Localidad.</li>
<li><strong>🏫 Vídeo 10 · Contextualiza: el centro</strong> El centro educativo dice mucho de tu intervención. Describiremos sus características con detalle pedagógico real: el claustro, el alumnado, el entorno, el PYP y los recreos inclusivos. Todo lo que el tribunal quiere ver — y lo que marca la diferencia entre una contextualización genérica y una que demuestra conocimiento. Con rúbrica real y checklist descargable: Contexto · Centro.</li>
<li><strong>🚪 Vídeo 11 · Contextualiza tu PA: las aulas</strong> Aquí entramos en el corazón de tu intervención. Trabajaremos el tipo de aula, su finalidad, localización, distribución y materiales, la estructuración del espacio y el censo de alumnado. También definiremos las medidas y recursos necesarios, y dejaremos claro cuáles son las funciones y objetivos de la PT en ese contexto. Con rúbrica real y checklist descargable: Contexto · Aulas.</li>
</ul>
<h3>📌 Al terminar este bloque tendrás</h3>
<ul>
<li>La localidad descrita con datos relevantes y bien estructurada</li>
<li>El centro contextualizado con criterio pedagógico real</li>
<li>Las aulas definidas con precisión: espacio, alumnado, recursos y papel de la PT</li>
</ul>
END;

up($pdo, 'temas', 14, $htmlTema14y46);
up($pdo, 'temas', 46, $htmlTema14y46);

// ── temas id=16 y id=48 — BLOQUE 5. ENTRAMADO CURRICULAR ─────
$htmlTema16y48 = <<<'END'
<p>Aquí empieza el núcleo de tu intervención. Este bloque es donde demuestras que sabes diseñar con criterio curricular real — y eso es exactamente lo que el tribunal quiere ver.</p>
<p>El entramado curricular no es copiar objetivos y criterios de evaluación de un decreto. Es tomar decisiones pedagógicas fundamentadas: qué trabajar, cómo adaptarlo, desde qué marco normativo y con qué nivel de exigencia para cada alumno o alumna. Aquí aprendes a hacerlo con rigor y con coherencia.</p>
<p>Este bloque marca la diferencia entre una programación que cumple... y una que convence.</p>
<h3>Lo que trabajamos en este bloque</h3>
<ul>
<li><strong>🕸️ Vídeo 16 · Nuestro entramado curricular</strong> Analizamos en profundidad qué aborda este apartado y cuáles son las posibles modalidades de intervención: ACS, ACI o Programa Específico. Trabajaremos los conceptos clave del diseño curricular — objetivos generales de etapa, descriptores operativos, criterios de evaluación, saberes básicos — y aprenderás a seleccionarlos, adaptarlos y organizarlos con sentido. Veremos cómo partir los contenidos de un curso educativo de forma estratégica y cuándo y cómo orientar al equipo docente al finalizar la planificación. Con rúbrica real y checklist descargable: Entramado curricular.</li>
<li><strong>🎯 Vídeo 17 · Programas específicos</strong> Los programas específicos tienen entidad propia dentro de la intervención PT y el tribunal lo sabe. Aprenderás qué son, dónde se desarrollan, quién los lleva a cabo y cuándo se aplican. Revisaremos los distintos tipos según el área de trabajo y cómo presentarlos de forma clara y justificada en tu programación. Con rúbrica real y checklist descargable: Programa específico.</li>
</ul>
<h3>📌 Al terminar este bloque tendrás</h3>
<ul>
<li>La modalidad de intervención elegida y argumentada: ACS y/o PE</li>
<li>El entramado curricular diseñado con los elementos normativos correctos y bien articulados</li>
<li>Los programas específicos definidos con precisión: qué, dónde, quién y cuándo</li>
<li>Un apartado curricular coherente, fundamentado y alineado con la rúbrica del tribunal</li>
</ul>
END;

up($pdo, 'temas', 16, $htmlTema16y48);
up($pdo, 'temas', 48, $htmlTema16y48);

// ── temas id=17 y id=49 — BLOQUE 6. METODOLOGÍA ──────────────
$htmlTema17y49 = <<<'END'
<p>La metodología es el corazón pedagógico de tu programación. Aquí demuestras que no solo sabes qué enseñar — sabes cómo hacerlo, por qué y con qué evidencia detrás.</p>
<p>Este es uno de los bloques más extensos del curso, y con razón. El tribunal quiere ver que conoces las metodologías activas e innovadoras, que sabes aplicarlas al contexto de la PT y que no las usas como adorno — las integras con coherencia y con propósito.</p>
<p>Aquí no vas a aprender metodologías de memoria. Vas a entender cuándo, cómo y por qué usarlas con tu alumnado.</p>
<h3>Lo que trabajamos en este bloque</h3>
<ul>
<li><strong>🔧 Vídeo 18 · Metodología general y específica</strong> Punto de partida del bloque. Revisamos los principios pedagógicos que sustentan toda intervención PT, las metodologías generales innovadoras y activas que puedes incorporar y las metodologías específicas propias de nuestra especialidad. La base sobre la que se construye todo lo que viene después.</li>
<li><strong>♿ Vídeo 19 · DUA: Diseño Universal para el Aprendizaje</strong> El DUA no es una tendencia — es un marco de referencia imprescindible en cualquier programación PT sólida. Estudiaremos las tres redes neuronales, los principios del nuevo DUA 3.0 y cómo se traduce en la práctica a través de la implicación, la representación y la expresión. Con ejemplos aplicados al aula.</li>
<li><strong>🤝 Vídeo 20 · Aprendizaje cooperativo y colaborativo</strong> Aprenderás a diferenciar cooperativo de colaborativo — un error frecuente que el tribunal detecta. Veremos los requisitos para que funcione, cómo intervenir como PT, cómo crear equipos con criterio, dinámicas de grupo reales y técnicas cooperativas simples con ejemplos concretos.</li>
<li><strong>🎲 Vídeo 21 · Aprendizaje Basado en el Juego (ABJ)</strong> El juego como metodología tiene nombre propio y fundamento teórico. Diferenciaremos juego, ABJ y gamificación, veremos cuatro formas de llevarlo a la práctica, ejemplos para infantil y primaria adaptados según NEE y herramientas digitales para enriquecerlo. Con la mirada de Francesco Tonucci siempre presente.</li>
<li><strong>🏆 Vídeo 22 · Gamificación I</strong> Qué es, qué aporta, cuáles son sus partes, sus tipos y cómo diseñarla con criterio pedagógico real. Con referencias sólidas y un ejemplo completo para que veas cómo se aplica en un contexto PT.</li>
<li><strong>🔐 Vídeo 23 · Microgamificación II</strong> Profundizamos en dos formatos muy potentes: el Breakout y el Escape Room. Con ejemplos reales y desarrollados para que puedas adaptarlos a tu alumnado y a tu contexto de aula.</li>
<li><strong>🔬 Vídeo 24 · ABP, ABR, APD, ABC, APS</strong> Un vídeo denso y muy completo. Trabajaremos en profundidad el Aprendizaje Basado en Proyectos, en Retos, por el Pensamiento de Diseño, por Competencias y en Servicio. Para cada uno: definición, funciones del profesorado y el alumnado, características y pasos de implementación. Con conexión directa al Trabajo Cooperativo y Colaborativo.</li>
<li><strong>📱 Vídeo 25 · Flipped Classroom, Mobile Learning y Robótica</strong> Tres metodologías con gran potencial en el aula PT. Aprenderás qué son, qué objetivos persiguen, sus características diferenciales y cómo implementarlas paso a paso en tu contexto de intervención.</li>
<li><strong>🌱 Vídeo 26 · Montessori, estaciones de aprendizaje y trabajo por rincones</strong> Tres enfoques con una filosofía común: el protagonismo del alumnado y la organización del espacio como recurso pedagógico. Con definiciones claras, características de cada uno y ejemplos reales de aplicación en aula PT.</li>
<li><strong>📁 Vídeo 27 · Portfolio, Visual Thinking, TEACCH y rutinas</strong> Cerramos el bloque con cuatro herramientas esenciales en la intervención PT. El portfolio como instrumento de seguimiento, el Visual Thinking con ejemplos visuales, el método TEACCH con sus pilares operativos y su estructuración, y las rutinas de aula — agenda, desayuno inteligente, relajación — como apoyo fundamental al alumnado con NEE.</li>
</ul>
<h3>📌 Al terminar este bloque tendrás</h3>
<ul>
<li>Un repertorio metodológico sólido, variado y argumentado</li>
<li>La capacidad de elegir cada metodología con criterio real según tu alumnado y tu contexto</li>
<li>Ejemplos concretos para infantil, primaria y alumnado con distintas NEE</li>
<li>Una metodología coherente con el DUA, la inclusión y la identidad docente que construiste en el Bloque 1</li>
</ul>
END;

up($pdo, 'temas', 17, $htmlTema17y49);
up($pdo, 'temas', 49, $htmlTema17y49);

// ── temas id=18 y id=50 — BLOQUE 7. SECUENCIA DIDÁCTICA ──────
$htmlTema18y50 = <<<'END'
<p>Una buena metodología sin una secuencia bien diseñada se queda a medias. Este bloque te enseña a estructurar el aprendizaje con lógica, con intención y con el ritmo que tu alumnado necesita.</p>
<p>La secuencia didáctica es el motor que hace avanzar el aprendizaje. No es una lista de actividades — es una arquitectura pedagógica con inicio, desarrollo y cierre, donde cada momento tiene un propósito claro y una conexión real con lo anterior y lo siguiente.</p>
<p>Aquí aprenderás a diseñarla con criterio, con estructura inclusiva y con la evidencia científica que respalda cada decisión.</p>
<h3>Lo que trabajamos en este bloque</h3>
<ul>
<li><strong>⚙️ Vídeo 28 · Secuencia didáctica</strong> Analizamos qué es la secuencia didáctica como motor del aprendizaje, cómo se articula en tres momentos — inicio, desarrollo y cierre — y qué ofrece cada uno al proceso de enseñanza-aprendizaje. Trabajaremos los principios de instrucción directa de Barak Rosenshine como referente científico, veremos ejemplos de secuencias reales según distintas comunidades autónomas y aprenderemos a diferenciar con precisión entre tareas, actividades y ejercicios — y el objetivo de cada uno. Con el concepto del menú didáctico: primer plato, segundo plato y postre.</li>
<li><strong>🏗️ Vídeo 29 · Estructura inclusiva</strong> Una secuencia didáctica en PT no puede diseñarse sin pensar en la inclusión desde el primer momento. Antes de estructurar cualquier actividad es necesario trabajar la anticipación, los apoyos, la adaptación y la graduación de la dificultad. Veremos cómo asesorar al equipo docente desde nuestra posición como PT, con ejemplos reales aplicados a lengua y matemáticas. Un vídeo que cierra el bloque con una mirada amplia, colaborativa y profundamente inclusiva.</li>
</ul>
<h3>📌 Al terminar este bloque tendrás</h3>
<ul>
<li>Una secuencia didáctica diseñada con estructura clara y propósito pedagógico real</li>
<li>El concepto de menú didáctico integrado en tu programación</li>
<li>La diferencia entre tarea, actividad y ejercicio dominada y aplicada con criterio</li>
<li>Una estructura inclusiva que anticipa, apoya y gradúa el aprendizaje desde el inicio</li>
<li>Argumentos y ejemplos para orientar al equipo docente desde tu rol PT</li>
</ul>
END;

up($pdo, 'temas', 18, $htmlTema18y50);
up($pdo, 'temas', 50, $htmlTema18y50);

// ── temas id=19 y id=51 — BLOQUE 8. OTROS ASPECTOS TÉCNICOS ──
$htmlTema19y51 = <<<'END'
<p>Los detalles técnicos no son relleno — son la prueba de que conoces la normativa, entiendes el contexto educativo completo y sabes cómo la PT se integra en el proyecto de centro.</p>
<p>Este bloque reúne aspectos que muchas opositoras tratan de forma superficial o directamente omiten. Y precisamente por eso, trabajarlos bien marca una diferencia real en la puntuación. El tribunal quiere ver que tu programación va más allá del aula — que conecta con el centro, con los planes institucionales y con la realidad del alumnado en su globalidad.</p>
<h3>Lo que trabajamos en este bloque</h3>
<ul>
<li><strong>📖 Vídeo 30 · Plan de lectura y razonamiento lógico-matemático</strong> Dos planes institucionales que toda programación PT debe contemplar. Trabajaremos el Plan de Lectura a partir de la normativa vigente (I. 21/06/2023): qué pretende, qué incluye, cómo se evalúa y cómo se planifica. Y el Plan de Razonamiento Matemático: para qué nace, qué trabajamos, cómo lo hacemos y cómo integrarlo en nuestra intervención con ideas concretas, objetivos claros y propuestas de evaluación y planificación reales.</li>
<li><strong>🧩 Vídeo 31 · Procesos cognitivos</strong> Los procesos cognitivos son la base invisible de todo aprendizaje — y nombrarlos con criterio en tu programación demuestra profundidad pedagógica. Identificaremos cuáles son, qué los define, por qué son importantes y qué estrategias concretas podemos usar para trabajarlos con el alumnado PT.</li>
<li><strong>👥 Vídeo 32 · Agrupamientos</strong> Los agrupamientos no son una cuestión organizativa — son una decisión pedagógica con impacto directo en la inclusión y en el aprendizaje. Estudiaremos su importancia, los tipos existentes y cómo usar los agrupamientos flexibles en función de la actividad, el objetivo y el alumnado. Con una secuencia de agrupamientos que demuestra inclusión real desde la estructura.</li>
<li><strong>🌍 Vídeo 33 · Actividades complementarias, extraescolares, planes y programas, y recreos inclusivos</strong> Preparamos al alumnado para la vida — y eso va mucho más allá del aula. Veremos qué son las actividades complementarias y extraescolares, cómo integrarlas con criterio de igualdad, qué planes y programas de centro son relevantes para la PT y cómo abordar los recreos inclusivos: normativa, objetivos, recursos humanos, ejemplos reales y planificación concreta.</li>
</ul>
<h3>📌 Al terminar este bloque tendrás</h3>
<ul>
<li>Los planes de lectura y razonamiento matemático integrados en tu programación con base normativa real</li>
<li>Los procesos cognitivos identificados y conectados con estrategias de intervención concretas</li>
<li>Una propuesta de agrupamientos flexible, inclusiva y pedagógicamente justificada</li>
<li>Las actividades complementarias, extraescolares y los recreos inclusivos desarrollados con criterio y con ejemplos</li>
</ul>
END;

up($pdo, 'temas', 19, $htmlTema19y51);
up($pdo, 'temas', 51, $htmlTema19y51);

// ── temas id=20 y id=52 — BLOQUE 9. RECURSOS ─────────────────
$htmlTema20y52 = <<<'END'
<p>Los recursos no son un listado para rellenar. Son decisiones pedagógicas que reflejan cómo entiendes el aprendizaje, cómo lo organizas y cómo re-aprovechas todo lo que tienes a tu alcance para que tu alumnado avance.</p>
<p>Un buen apartado de recursos demuestra que conoces las herramientas de tu especialidad, que sabes seleccionarlas con criterio y que estás al día en innovación educativa. Aquí aprenderás a presentarlos de forma estructurada, variada y coherente con todo lo que has construido en los bloques anteriores.</p>
<h3>Lo que trabajamos en este bloque</h3>
<ul>
<li><strong>👩‍👩‍👧‍👦 Vídeo 34 · Recursos personales, materiales y ambientales</strong> Punto de partida del bloque. Identificaremos los distintos tipos de recursos y aprenderemos a presentarlos con criterio pedagógico real. Trabajaremos los recursos materiales desde el esquema TIC-TAC-TRIC-TEP, la Rueda DUA como herramienta para seleccionar recursos con intención inclusiva, los recursos educativos específicos de la PT, los recursos personales — profesionales implicados en la intervención — y los recursos ambientales como el espacio y su organización.</li>
<li><strong>💻 Vídeo 35 · Artefactos digitales</strong> Los artefactos digitales son mucho más que herramientas tecnológicas — son productos de aprendizaje con valor pedagógico propio. Aprenderás qué son, cómo diferenciar TIC de TAC y a diseñar un Entorno Personal de Aprendizaje (PLE) para tu alumnado. Veremos una amplia tipología: animaciones, curación de contenidos, blogs, cómics, apps, geolocalización, infografías, mapas mentales, murales digitales, podcast, realidad aumentada, música digital, presentaciones, robótica, tutoriales, vídeo, wikis, líneas de tiempo, libros electrónicos, redes sociales, gamificación y plataformas de aprendizaje.</li>
<li><strong>🤖 Vídeo 36 · Inteligencia artificial</strong> La IA ya forma parte del ecosistema educativo — y una maestra PT del siglo XXI necesita conocerla, usarla con criterio y saber cómo integrarla en su práctica. Aprenderás qué es la inteligencia artificial, qué herramientas concretas puedes utilizar, cómo diseñar prompts efectivos para sacarles el máximo partido y cuáles son los distintos tipos de IA aplicados a la educación. Incluye checklist descargable: Prompts efectivos para PT.</li>
</ul>
<h3>📌 Al terminar este bloque tendrás</h3>
<ul>
<li>Los recursos personales, materiales y ambientales organizados con criterio y coherencia inclusiva</li>
<li>Un repertorio de artefactos digitales variado y argumentado pedagógicamente</li>
<li>Conocimiento real y aplicado de la inteligencia artificial como recurso educativo</li>
<li>Un apartado de recursos que refleja actualización, innovación y dominio de la especialidad</li>
</ul>
END;

up($pdo, 'temas', 20, $htmlTema20y52);
up($pdo, 'temas', 52, $htmlTema20y52);

// ── temas id=21 y id=53 — BLOQUE 10. EVALUACIÓN DEL APRENDIZAJE
$htmlTema21y53 = <<<'END'
<p>Evaluar es conocer el punto de partida, acompañar el proceso y reconocer el progreso de cada alumno o alumna desde su propia realidad. Este bloque te enseña a hacerlo con rigor, con equidad y con las herramientas adecuadas.</p>
<p>La evaluación es uno de los apartados que más peso tiene en la rúbrica del tribunal — y uno de los que más errores concentra. Aquí aprenderás a construirla desde la normativa real, con instrumentos variados, con criterios de ponderación justificados y con una mirada genuinamente inclusiva.</p>
<p>Una evaluación bien diseñada no cierra la programación. La completa.</p>
<h3>Lo que trabajamos en este bloque</h3>
<ul>
<li><strong>🔍 Vídeo 37 · Aspectos generales</strong> Punto de partida imprescindible. Veremos qué es la evaluación en el contexto PT, qué dice la normativa vigente, qué tipos de evaluación existen, qué técnicas e instrumentos tenemos a nuestra disposición y qué consejos clave debes tener presentes antes de diseñar cualquier propuesta evaluadora.</li>
<li><strong>🟢 Vídeo 38 · Evaluación inicial</strong> La evaluación inicial es el primer acto pedagógico de cualquier intervención — y el tribunal lo sabe. Aprenderás qué es, qué tipos existen, qué herramientas puedes usar para llevarla a cabo y cómo presentarla en tu programación de forma que demuestre criterio y coherencia con el resto del documento.</li>
<li><strong>📋 Vídeo 39 · Técnicas e instrumentos</strong> Un vídeo práctico y muy completo. Trabajaremos las técnicas de evaluación y los instrumentos más relevantes para la PT: el cuaderno docente, la rúbrica, la lista de control, la autoevaluación, las escalas de valoración, el diario de equipo y el portfolio de progreso. Con ejemplos reales de cada uno para que puedas adaptarlos a tu alumnado y a tu contexto de intervención.</li>
<li><strong>⚖️ Vídeo 40 · Ponderación y calificación</strong> Uno de los vídeos más técnicos y más necesarios del curso. Aprenderás a diseñar una ponderación personalizada, a elegir entre distintas opciones según tu alumnado y a aplicar los criterios de calificación tanto en Andalucía como en el resto de España. Trabajaremos conceptos clave como NI, EP y C, el triángulo de logro, el muro del EP, la calificación en sobresaliente y las observaciones especiales para las familias.</li>
<li><strong>📅 Vídeo 41 · Cierre de las situaciones de aprendizaje y cronograma</strong> El cierre de cada Situación de Aprendizaje necesita coherencia y estructura. Aprenderás cómo conectar la evaluación con las SdA, cómo diseñar un cronograma realista y bien organizado y cómo presentarlo de forma visual y clara. Con ejemplos reales para que puedas adaptarlo a tu programación sin partir de cero.</li>
</ul>
<h3>📌 Al terminar este bloque tendrás</h3>
<ul>
<li>Un sistema de evaluación completo, normativo y coherente con tu intervención</li>
<li>La evaluación inicial diseñada con herramientas concretas y justificadas</li>
<li>Un repertorio de técnicas e instrumentos variado y aplicado a la realidad PT</li>
<li>La ponderación y calificación resueltas con criterio propio y base normativa</li>
<li>El cronograma de las SdA cerrado y listo para incluir en tu programación</li>
</ul>
END;

up($pdo, 'temas', 21, $htmlTema21y53);
up($pdo, 'temas', 53, $htmlTema21y53);

// ── temas id=22 y id=54 — BLOQUE 11. ENTORNO ─────────────────
$htmlTema22y54 = <<<'END'
<p>La intervención PT nunca ocurre en solitario. Este bloque te enseña a construir los puentes que hacen que tu trabajo tenga impacto real más allá del aula.</p>
<p>Una de las señas de identidad de una buena maestra PT es saber trabajar en red. Con las familias, con el equipo docente, con los especialistas. La coordinación no es un apartado administrativo — es una decisión pedagógica que multiplica el efecto de tu intervención y que el tribunal valora profundamente.</p>
<p>Aquí aprenderás a presentarla con criterio, con estructura y con ejemplos concretos que demuestren que entiendes tu rol dentro de la comunidad educativa.</p>
<h3>Lo que trabajamos en este bloque</h3>
<ul>
<li><strong>👨‍👩‍👧 Vídeo 42 · Familia</strong> La familia es la primera aliada en el proceso educativo de cualquier alumno o alumna con NEE — y como PT tienes un papel clave en esa relación. Trabajaremos el concepto de corresponsabilidad, los aspectos clave de la relación familia-escuela, los canales de comunicación más efectivos, cómo organizar los calendarios de reuniones y sobre qué temas podemos y debemos hablar con las familias desde nuestra especialidad.</li>
<li><strong>👩‍🏫 Vídeo 43 · Compañeros</strong> La coordinación con el equipo docente es una de las funciones más importantes de la PT — y una de las menos desarrolladas en las programaciones. Aprenderás a diferenciar coordinación de colaboración, qué nos ofrece cada tipo de trabajo conjunto, cuáles son los momentos clave para coordinarse, cómo mantener una actitud motivadora dentro del equipo y sobre qué aspectos concretos podemos hablar con nuestros compañeros y compañeras.</li>
</ul>
<h3>📌 Al terminar este bloque tendrás</h3>
<ul>
<li>La coordinación con familias estructurada con canales, calendarios y contenidos concretos</li>
<li>La colaboración con el equipo docente desarrollada con criterio, con ejemplos y con los puntos clave que valora el tribunal</li>
<li>Un apartado de entorno que refleja una visión holística, inclusiva y comunitaria de la intervención PT</li>
<li>La demostración de que tu rol va mucho más allá del aula</li>
</ul>
END;

up($pdo, 'temas', 22, $htmlTema22y54);
up($pdo, 'temas', 54, $htmlTema22y54);

// ── temas id=23 y id=55 — BLOQUE 12. EVALUACIÓN DE LA PRÁCTICA
$htmlTema23y55 = <<<'END'
<p>Evaluar nuestra propia práctica es crecer como maestr@. Este bloque te enseña a cerrar el ciclo de mejora con rigor, con honestidad y con las herramientas adecuadas.</p>
<p>La evaluación de la práctica docente es mucho más que un apartado que hay que incluir porque lo pide la convocatoria. Es la demostración de que tienes una actitud reflexiva hacia tu propio trabajo, que no das nada por sentado y que entiendes la enseñanza como un proceso en permanente mejora.</p>
<p>El tribunal lo busca. Y cuando lo encuentra bien desarrollado, lo valora.</p>
<h3>Lo que trabajamos en este bloque</h3>
<ul>
<li><strong>🔄 Vídeo 44 · Evalúa, identifica y mejora</strong> Un vídeo directo, práctico y muy completo. Trabajaremos las ideas clave que sostienen la evaluación de la práctica docente, cuáles son los momentos en los que debe producirse — inicial, procesual y final — y quiénes son los agentes implicados en ese proceso de valoración: el propio docente, el equipo, las familias y el alumnado. Veremos ejemplos concretos de cómo llevarlo a la práctica y qué herramientas puedes usar para recoger evidencias reales de tu evolución como maestra PT.</li>
</ul>
<h3>📌 Al terminar este bloque tendrás</h3>
<ul>
<li>La evaluación de tu práctica docente diseñada con momentos, agentes y herramientas concretas</li>
<li>Ejemplos reales de cómo presentar este apartado de forma que demuestre reflexión y criterio profesional</li>
<li>Un cierre del ciclo de mejora coherente con todo lo construido en los bloques anteriores</li>
<li>La actitud reflexiva que el tribunal espera ver en una maestra PT que aspira a su plaza</li>
</ul>
END;

up($pdo, 'temas', 23, $htmlTema23y55);
up($pdo, 'temas', 55, $htmlTema23y55);

// ── temas id=24 y id=56 — BLOQUE 13. TERMINA TU PA DE 10 ─────
$htmlTema24y56 = <<<'END'
<p>Has construido una programación sólida, coherente y con identidad propia. Ahora toca cerrarla como se merece — con la misma intención y el mismo cuidado con el que empezaste.</p>
<p>El cierre de TU PA es la última oportunidad de dejar huella en el tribunal. Una conclusión bien escrita, una contraportada con personalidad y unas indicaciones finales bien resueltas son la diferencia entre una programación que se olvida y una que se recuerda.</p>
<p>Este bloque es corto en vídeos pero enorme en impacto. No lo subestimes.</p>
<h3>Lo que trabajamos en este bloque</h3>
<ul>
<li><strong>💬 Vídeo 45 · Conclusión y referencias</strong> El cierre de tu programación merece una conclusión que esté a la altura de todo lo que has construido. Aprenderás cuáles son las ideas clave que debe recoger, cómo redactarla con coherencia y con emoción pedagógica real y cómo anotar las referencias bibliográficas de forma correcta y rigurosa. Con ejemplos concretos para que puedas adaptarlos a tu propio estilo y a tu hilo conductor.</li>
<li><strong>🎨 Vídeo 46 · Contraportada</strong> La contraportada es el último elemento que el tribunal ve — y también puede ser el más memorable. Trabajaremos su objetivo, cómo construir un lema que te represente, cómo diseñarla con criterio estético y cómo incluir una dedicatoria que humanice tu documento y lo haga único. Porque los tribunales también son personas, y una programación que emociona se recuerda.</li>
<li><strong>📝 Vídeo 47 · Indicaciones finales</strong> El último vídeo antes de la defensa. Tres claves que no puedes olvidar: claridad, coherencia y adecuación. Aprenderás cómo hacer una revisión final al detalle — qué mirar, en qué orden y con qué criterio — y por qué tu programación debe ser siempre un documento abierto y flexible, preparado para mejorar con cada convocatoria.</li>
</ul>
<h3>📌 Al terminar este bloque tendrás</h3>
<ul>
<li>Una conclusión que cierra con coherencia, con criterio y con emoción pedagógica real</li>
<li>Las referencias bibliográficas correctamente anotadas y organizadas</li>
<li>Una contraportada con diseño, lema y dedicatoria que hace que tu programación sea única e inolvidable</li>
<li>Una revisión final completa que garantiza claridad, coherencia y adecuación en cada apartado</li>
</ul>
END;

up($pdo, 'temas', 24, $htmlTema24y56);
up($pdo, 'temas', 56, $htmlTema24y56);

// ── temas id=25 y id=57 — BLOQUE 14. DEFENSA ─────────────────
$htmlTema25y57 = <<<'END'
<p>Has construido una programación de 10. Ahora toca defenderla como te mereces — con seguridad, con criterio y con la presencia de un@ maestr@ PT que sabe exactamente lo que ha hecho y por qué.</p>
<p>La defensa oral es el momento en el que todo el trabajo cobra vida. No basta con tener una buena programación — hay que saber presentarla, reducirla con inteligencia y transmitir al tribunal que detrás de ese documento hay una profesional con dominio, con convicción y con identidad docente propia.</p>
<p>Este bloque es el broche final de tu preparación. Y uno de los más transformadores de todo el curso.</p>
<h3>Lo que trabajamos en este bloque</h3>
<ul>
<li><strong>🖊️ Vídeo 48 · Un dibujo puede ser tu mayor aliado</strong> La pizarra es una herramienta poderosa en la defensa oral — y muy pocas opositoras la usan con criterio. Aprenderás por qué puede marcar la diferencia, cuáles son sus ventajas reales, cómo controlar su uso para que sume y no reste y qué ejemplos concretos puedes aplicar en tu defensa.</li>
<li><strong>🎯 Vídeo 49 · Defensa I</strong> Primer bloque de trabajo sobre la defensa. Aquí empezamos con la estética de la presentación oral y la reducción inteligente del contenido: cómo seleccionar lo esencial de tu programación, cómo organizarlo para que fluya con naturalidad ante el tribunal y cómo construir una estructura que transmita seguridad desde el primer segundo.</li>
<li><strong>🎯 Vídeo 50 · Defensa II</strong> Continuamos profundizando en la reducción inteligente y en cómo presentar los apartados más técnicos de tu programación de forma clara, ágil y convincente. Con ejemplos reales de cómo condensar sin perder profundidad ni coherencia.</li>
<li><strong>🎯 Vídeo 51 · Defensa III</strong> El tercer vídeo de defensa trabaja los momentos más delicados de la exposición oral: cómo arrancar, cómo cerrar y cómo gestionar las transiciones entre apartados para que el tribunal perciba un discurso fluido, seguro y bien construido.</li>
<li><strong>🔒 Vídeo 52 · Prepara tu encerrona</strong> La encerrona es uno de los momentos más intensos del proceso de oposición — y también uno de los más decisivos. Aprenderás cómo prepararte para ese tiempo de trabajo previo a la defensa, cómo organizar tus materiales, cómo gestionar los nervios y cómo llegar al momento de exponer con la máxima claridad mental y la mejor versión de ti misma.</li>
<li><strong>💛 Vídeo 53 · Consejos finales</strong> El último vídeo del curso. Un cierre cercano, honesto y profundamente motivador. Todo lo que necesitas escuchar antes de entrar a defender tu programación — y todo lo que me hubiera gustado que alguien me dijera a mí antes de hacerlo. Porque tú has hecho el trabajo. Ahora solo tienes que demostrarlo.</li>
</ul>
<h3>📌 Al terminar este bloque tendrás</h3>
<ul>
<li>La pizarra integrada como recurso estratégico en tu defensa oral</li>
<li>Tu programación reducida de forma inteligente y estructurada para la exposición</li>
<li>Una defensa organizada con inicio, desarrollo y cierre que transmite seguridad y dominio</li>
<li>La encerrona preparada con materiales, estrategia y gestión emocional</li>
<li>Todo lo que necesitas para entrar al tribunal con convicción y con la mejor versión de ti misma</li>
</ul>
END;

up($pdo, 'temas', 25, $htmlTema25y57);
up($pdo, 'temas', 57, $htmlTema25y57);

// ══════════════════════════════════════════════════════════════
// Respuesta final
// ══════════════════════════════════════════════════════════════
echo json_encode(['ok' => true, 'actualizados' => $actualizados]);
