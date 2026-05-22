<?php
// Migración única: rellenar descripciones de cursos y temas
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db-connect.php';
if (($_GET['key'] ?? '') !== SETUP_KEY) { http_response_code(403); exit; }
$pdo = obtenerPDO();

$log = [];

function upd(PDO $pdo, string $tabla, int $id, string $desc, array &$log): void {
    try {
        $stmt = $pdo->prepare("UPDATE $tabla SET descripcion = ? WHERE id = ?");
        $stmt->execute([$desc, $id]);
        $log[] = ['ok' => true, 'msg' => "$tabla id=$id actualizado"];
    } catch (Exception $e) {
        $log[] = ['ok' => false, 'msg' => "$tabla id=$id: " . $e->getMessage()];
    }
}

// ════════════════════════════════════════════════════
// CURSOS
// ════════════════════════════════════════════════════

// Curso 6 · Ten un temario de 10
upd($pdo, 'cursos', 6, '<p>En un proceso de oposición como al que te estas enfrentando lo más común es estudiar durante meses y llegar al examen sintiéndose preparad@. Escribir, desarrollar, citar. Y cuando recibes la nota... un 6. Un 7. Nunca ese 10 que sabes que mereces.</p>
<p>El problema casi nunca es la falta de conocimiento. El problema es no saber transformar lo que sabes en un tema que el tribunal recuerde. Este curso existe para cambiar eso.</p>
<p>No es un curso de memorización. Es un entrenamiento estratégico para que dejes de soltar datos y empieces a construir temas con estructura, con identidad docente propia y con el criterio pedagógico que marca la diferencia entre un tema correcto y un tema de 10.</p>
<p>Aquí aprenderás a diseñar un índice estratégico, a escribir introducciones que enganchan, fundamentaciones que convencen y conclusiones que dejan huella. A citar con criterio sin saturar. A gestionar el tiempo real del examen sin bloquearte. Y a dominar los 25 temas del temario PT con una visión global, conectada y profundamente actualizada.</p>
<p>Porque tu esfuerzo merece un 10 y tú puedes conseguirlo; este curso te enseñará cómo.</p>
<p>¡Hola! Me alegra muchísimo que estés aquí. Si has llegado a este curso es porque sientes que algo falla. Estudias, subrayas, repasas — y aun así tus temas no acaban de sonar como deberían. Como los de alguien que merece una plaza.</p>
<p>Eso tiene solución. Y está aquí.</p>
<p>Quiero que sepas desde el principio que este no es un curso para estudiar más. Es un curso para estudiar mejor — para entender qué busca el tribunal en la Parte A del examen, cómo construir un tema con arquitectura real y cómo escribir con fluidez, con criterio y con esa identidad docente propia que hace que un tema destaque entre todos los demás.</p>
<p>Vamos a trabajar juntas desde los conceptos previos hasta la revisión final. Con vídeos cortos y directos, con ejemplos reales, con plantillas que puedes usar desde el primer día y con una visión global del temario que conecta los 25 temas de forma que nunca más los veas como piezas sueltas.</p>
<p>Mi primer consejo antes de empezar: no te saltes el Bloque 1 aunque tengas prisa por llegar al temario. La arquitectura invisible de un tema excelente se construye antes de escribir la primera palabra. Y ese es exactamente el punto de partida.</p>
<p>Tienes todo lo que necesitas para hacer un tema de 10. Ahora vamos a demostrarlo juntas.</p>', $log);

// Cursos 9 y 13 · Situación de Aprendizaje (ordinaria y específica)
$desc_sa = '<p>Hay opositoras que saben qué es una Situación de Aprendizaje. Y hay opositoras que saben diseñarla, justificarla y defenderla ante un tribunal con criterio, con coherencia y con la seguridad de quien domina lo que hace. Este curso existe para que seas de las segundas.</p>
<p>Porque una Situación de Aprendizaje no es una lista de actividades con un título bonito. Es una arquitectura pedagógica completa — con intención curricular real, con un producto que da sentido al aprendizaje, con evaluación integrada desde el primer momento y con una mirada inclusiva que no se queda en el papel.</p>
<p>Aquí vas a aprender a construirla desde cero, paso a paso y con criterio propio. Desde la elección del nivel educativo y la organización anual, hasta la secuencia didáctica, la evaluación y la defensa oral ante el tribunal. Con ejemplos reales, plantillas descargables y una guía de apoyo según NEAE que marca la diferencia.</p>
<p>No importa en qué etapa educativa intervengas ni cuánta experiencia tengas previa. Este curso está diseñado para que cualquier opositora a PT pueda construir una Situación de Aprendizaje que el tribunal recuerde.</p>
<p>Porque si no sabes qué vas a evaluar, no sabes qué estás enseñando. Y aquí vas a tenerlo muy claro.</p>
<p>¡Hola! Me alegra mucho que estés aquí. Quiero que sepas desde el principio que este no es un curso para copiar una plantilla y rellenar huecos. Es un curso para entender qué es una Situación de Aprendizaje de verdad, qué decisiones pedagógicas hay detrás de cada apartado y cómo justificarlas ante un tribunal con seguridad y con criterio propio.</p>
<p>Vamos a trabajar juntas desde los pasos previos hasta la defensa oral. Con calma, con orden y con ejemplos reales que puedes adaptar a tu contexto, tu alumnado y tu etapa educativa.</p>
<p>Mi primer consejo antes de empezar: no vayas con prisa por llegar al final. Cada vídeo tiene su propósito. Cada bloque construye sobre el anterior. Y una Situación de Aprendizaje bien pensada siempre se nota — el tribunal lo percibe desde la primera línea.</p>
<p>Tienes todo lo que necesitas para construir una SA de 10. Ahora vamos a demostrarlo juntas.</p>';
upd($pdo, 'cursos', 9, $desc_sa, $log);
upd($pdo, 'cursos', 13, $desc_sa, $log);

// Curso 14 · Técnicas de preparación
upd($pdo, 'cursos', 14, '<p>Hay opositoras que estudian cuatro horas diarias durante meses y llegan al examen sintiéndose vacías. Como si todo lo que han leído se hubiera evaporado de un día para otro. Como si estudiar más no fuera la solución — pero tampoco supieran cuál es.</p>
<p>La solución existe. Y tiene nombre: estudiar mejor.</p>
<p>Este curso no te enseña qué estudiar. Te enseña cómo hacerlo de forma que tu cerebro retenga, consolide y recupere la información cuando más la necesitas — el día del examen.</p>
<p>Desde la neurociencia aplicada al estudio hasta las técnicas de memoria más potentes, pasando por la planificación estratégica y la gestión emocional ante los exámenes. Aquí encontrarás las herramientas que marcan la diferencia entre una preparación que agota y una preparación que avanza.</p>
<p>Porque preparar unas oposiciones a PT no es una carrera de velocidad. Es una maratón — y para ganarla necesitas conocerte, organizarte y cuidarte. Este curso te enseña a hacer las tres cosas.</p>
<p>¡Hola! Me alegra mucho que estés aquí. Si has llegado a este curso es porque en algún momento has sentido que estudias horas y horas — y que al día siguiente apenas recuerdas lo que repasaste. Que tu cabeza absorbe, pero no retiene. Que llegas al examen con la sensación de que todo lo que estudiaste se ha ido por algún lugar que no encuentras.</p>
<p>Eso no es falta de capacidad. Es falta de método. Y eso tiene solución.</p>
<p>En este curso vamos a trabajar desde la raíz: cómo funciona tu memoria, qué técnicas de estudio realmente funcionan — y cuáles te hacen perder tiempo sin darte cuenta — cómo organizar tu preparación de forma sostenible y cómo gestionar los nervios y la ansiedad para que no te roben el rendimiento que mereces.</p>
<p>No vas a encontrar aquí recetas mágicas. Vas a encontrar herramientas reales, contrastadas por la neurociencia y probadas en la preparación real de oposiciones PT. Con ejemplos concretos, con recursos descargables y con un enfoque que pone tu bienestar en el centro — porque una opositora que cuida cómo estudia llega más lejos que una que solo estudia más.</p>
<p>Mi primer consejo antes de empezar: haz el test de autodiagnóstico del Bloque 0 antes de continuar. Conocerte es el primer paso para mejorar. Y aquí empezamos por ahí.</p>', $log);

// Curso 15 · Resolución estratégica de Supuestos Prácticos
upd($pdo, 'cursos', 15, '<p>El supuesto práctico es la parte del examen que más miedo da — y la que más puntos puede darte si sabes cómo resolverlo.</p>
<p>No porque tengas que saber más que nadie. Sino porque tienes que saber pensar, organizar y responder con criterio en un tiempo limitado, con un caso real delante y un tribunal que busca algo muy concreto: una maestra PT que sabe lo que hace y por qué lo hace.</p>
<p>Este curso te enseña exactamente eso. Vídeo a vídeo, bloque a bloque, aprenderás a leer un supuesto con cabeza, a extraer la información clave, a estructurar tu respuesta con coherencia y a desarrollar cada apartado — desde la introducción hasta la conclusión — con el criterio pedagógico y normativo que el tribunal valora.</p>
<p>Trabajaremos la intervención a nivel de centro, de aula, individual y con el entorno. La evaluación del aprendizaje y de la práctica docente. La estrategia para gestionar el tiempo en el examen. Y la práctica real con supuestos completos resueltos para Discapacidad Auditiva, Visual, Intelectual, Motórica y TEA.</p>
<p>Con checklists, autoevaluación, documentos descargables y tres bonus que incluyen resoluciones completas de PE, ACS y ACI.</p>
<p>Porque resolver un supuesto de 10 no es cuestión de suerte. Es cuestión de método. Y ese método está aquí.</p>
<p>¡Hola! Me alegra enormemente que estés aquí. Si has llegado a este curso es porque el supuesto práctico te genera dudas, te bloquea o sientes que por mucho que estudies no acabas de saber cómo enfrentarte a él el día del examen. Y quiero que sepas que eso es más normal de lo que crees.</p>
<p>El supuesto práctico no es difícil porque requiera saber más. Es difícil porque requiere pensar de otra manera — leer con criterio, organizar con rapidez y escribir con coherencia bajo presión. Y eso se entrena. Exactamente igual que cualquier otra habilidad.</p>
<p>Mi consejo antes de empezar: no te saltes los primeros bloques aunque te parezcan teóricos. La diferencia entre un supuesto mediocre y uno de 10 casi siempre está en la comprensión del caso y en la coherencia de la respuesta — no en la cantidad de contenido que metes. Menos es más. Mejor es siempre mejor.</p>
<p>Tienes todo lo que necesitas para resolver tu supuesto con criterio, con estructura y con seguridad. Vamos a demostrarlo.</p>', $log);


// ════════════════════════════════════════════════════
// TEMAS · Curso 6 (Ten un temario de 10)
// ════════════════════════════════════════════════════

// Tema 7 · BLOQUE 2. Partes comunes de impacto
upd($pdo, 'temas', 7, '<p><em>Hay tres apartados que aparecen en todos los temas — y que la mayoría de opositoras resuelve de forma mecánica y sin criterio. Introducción, fundamentación y conclusión son las tres oportunidades de dejar huella que más se desaprovechan. Aquí aprenderás a construirlas con impacto real.</em></p>
<p>Porque una introducción que engancha, una fundamentación que convence y una conclusión que cierra con elegancia no son casualidad — son decisiones pedagógicas conscientes. Y ese es exactamente el objetivo de este bloque.</p>
<p><strong>Vídeo 4 · Una buena introducción marca la diferencia</strong> La introducción es la llave de entrada a tu tema — y tiene que abrir con fuerza. Aprenderás qué elementos no pueden faltar, cuáles son los errores más frecuentes que hacen que una introducción pierda impacto y cómo construirla con tres ejemplos reales que puedes adaptar a cualquier tema del temario.</p>
<p><strong>Vídeo 5 · Fundamentación: independiente o integrada</strong> La fundamentación normativa y bibliográfica es uno de los apartados más valorados — y uno de los más mal resueltos. Aquí aprenderás a desglosar el marco legislativo con criterio, a seleccionar los referentes bibliográficos más potentes y a decidir cuándo presentar la fundamentación de forma independiente y cuándo integrarla en el desarrollo del tema sin repetir ni saturar.</p>
<p><strong>Vídeo 6 · Concluye sin repetir. Crea tu broche de oro</strong> La conclusión no es un resumen — es un cierre con personalidad. Aprenderás qué dice la convocatoria sobre este apartado, cuáles son los puntos clave que debe incluir y cómo construirla de forma que deje al tribunal con la sensación de haber leído algo completo, coherente y bien pensado.</p>', $log);

// Tema 8 · BLOQUE 3. Desarrolla con rigor y actualización
upd($pdo, 'temas', 8, '<p><em>Un tema bien construido necesita referencias sólidas. No para rellenar — para demostrar que tu conocimiento está actualizado, fundamentado y conectado con la realidad educativa actual. Este bloque te enseña a citar con criterio, con eficacia y con las normas APA 7 como aliadas.</em></p>
<p>Citar bien no es una cuestión estética — es una señal de rigor profesional que el tribunal valora. Y citar mal puede restar puntos que has ganado con mucho esfuerzo. Aquí aprenderás a hacerlo con seguridad.</p>
<p><strong>Vídeo 7 · Referencias</strong> Por qué las referencias marcan la diferencia, qué tipos existen — bibliográficas, legislativas y webgráficas — y cómo y cuándo incorporarlas en el tema sin saturar ni interrumpir el flujo de la escritura. Aprenderás dónde colocarlas con criterio estratégico para que sumen sin restar y cómo seleccionar las más potentes para cada tema del temario.</p>
<p><strong>Vídeo 8 · Normas APA 7</strong> Las normas APA no tienen que ser un problema — pueden ser tu mejor herramienta de credibilidad. Aquí aprenderás a citar según APA 7 con claridad y sin errores, las mejores formas de resumir y parafrasear con corrección y cómo escoger referencias actualizadas cuando el tiempo de preparación es limitado.</p>', $log);

// Tema 9 · BLOQUE 4. Estructura eficiente
upd($pdo, 'temas', 9, '<p><em>Saber el contenido no es suficiente si no sabes gestionar el tiempo, organizar el papel y cuidar los detalles que el tribunal percibe antes de leer la primera línea. Este bloque te enseña a escribir con eficiencia, con criterio visual y sin perder puntos por errores evitables.</em></p>
<p>La estructura externa de un tema — cómo se ve antes de leerlo — es también un mensaje. Un mensaje de orden mental, de profesionalidad y de dominio. Aquí aprenderás a transmitirlo con intención.</p>
<p><strong>Vídeo 9 · Adapta tus temas a tu ritmo y tiempo real</strong> La gestión del tiempo es una habilidad que se entrena — y que puede salvarte el examen. Aprenderás a distribuir el tiempo con criterio entre los diferentes apartados del tema, a evitar el error más frecuente de atascarse en la introducción y a usar la técnica de escritura por bloques para avanzar con fluidez y sin bloqueos incluso bajo presión.</p>
<p><strong>Vídeo 10 · Diseña con estrategia y gusto</strong> La presentación visual de un tema es también parte de la puntuación. Aprenderás a organizar visualmente el contenido para mejorar la legibilidad sin perder tiempo, a cuidar márgenes, títulos y jerarquías con criterio.</p>
<p><strong>Vídeo 11 · Ortografía y atención — no pierdas puntos tontos</strong> Los errores ortográficos y de presentación son los más fáciles de evitar — y los que más duelen cuando restan puntos a un tema bien desarrollado.</p>', $log);

// Tema 10 · BLOQUE 5. Temario
upd($pdo, 'temas', 10, '<p><em>Este es el bloque que diferencia este curso de todos los demás. Aquí no trabajamos los 25 temas de forma aislada — los trabajamos de forma conectada, con visión global, con criterio de respuesta educativa real y con la profundidad que el tribunal espera en cada perfil de NEE.</em></p>
<p>Porque los 25 temas del temario PT no son 25 piezas sueltas. Son un sistema. Y cuando el tribunal percibe que lo entiendes como tal, la puntuación lo refleja.</p>
<p><strong>Vídeo 12 · Visión global del temario</strong> — <strong>Vídeos 13-25:</strong> Temas 1-2 (Educación Especial e inclusión), Tema 3 (Identificación y evaluación), Temas 4-5 (Centro ordinario vs. específico), Temas 6-9 (Recursos personales y materiales), Temas 10-13 (Currículo EI y EP), estructura de los temas de discapacidad, Discapacidad Auditiva, Visual, Motora, Intelectual, TGC/TDAH, TEA, y Altas Capacidades.</p>
<p>Cada perfil de NEE trabajado con estructura, criterio normativo y respuesta educativa real. Las conexiones entre temas identificadas y aprovechadas para reducir carga cognitiva y ganar profundidad. Un temario actualizado, coherente y listo para escribir con seguridad el día del examen.</p>', $log);

// Tema 11 · BLOQUE 6. Claves finales
upd($pdo, 'temas', 11, '<p><em>Tienes el contenido, tienes la estructura, tienes las referencias. Ahora toca lo más importante: convertir todo eso en un tema con voz propia. Este bloque te enseña a personalizar sin inventar, a revisar con criterio y a llegar al examen con la cabeza preparada para dar lo mejor de ti.</em></p>
<p>Porque la diferencia entre un tema correcto y un tema de 10 casi siempre está en los últimos detalles — en la coherencia, en la identidad docente y en saber revisar con los ojos del tribunal.</p>
<p><strong>Vídeo 26 · Claves finales I: personaliza tus temas sin inventar</strong> El mayor error que cometen las opositoras al intentar diferenciarse es añadir cosas que no dominan. Aquí aprenderás qué no hacer, cómo incorporar tu experiencia previa y tu formación continua de forma natural y creíble, cómo desarrollar tu marca inclusiva como seña de identidad pedagógica.</p>
<p><strong>Vídeo 27 · Claves finales II: ¿cómo revisar un tema antes del examen?</strong> La revisión no es releer — es analizar con tres niveles de profundidad: estructura, técnica y estrategia. Aprenderás a detectar, anotar y repasar de forma progresiva con las fases de corrección distribuidas en el esquema cinco-cinco-cinco.</p>', $log);


// ════════════════════════════════════════════════════
// TEMAS · Curso 7 y 12 (Programación ordinaria y específica)
// ════════════════════════════════════════════════════

$desc_prog_bloque4 = '<p><em>Tu programación existe por y para tu alumnado. Este bloque te enseña a describirlo con rigor, con sensibilidad y con la profundidad que el tribunal espera ver.</em></p>
<p>Conocer a tu alumnado no es solo saber su diagnóstico. Es entender sus fortalezas, sus barreras, su nivel de competencia curricular y las necesidades específicas que van a guiar toda tu intervención. Aquí aprendes a plasmarlo de forma clara, organizada y pedagógicamente sólida.</p>
<p>Este bloque es uno de los que más diferencia a una programación superficial de una que realmente convence.</p>
<p><strong>Vídeo 12 · ¿Qué debemos analizar?</strong> Antes de escribir, necesitas saber qué datos son realmente relevantes y de dónde nacen.</p>
<p><strong>Vídeo 13 · Usa esta guía</strong> Una guía práctica y detallada para describir cada apartado del alumnado con criterio.</p>
<p><strong>Vídeo 14 · Elige tu nivel de competencia curricular</strong> El nivel de competencia curricular es uno de los pilares de tu intervención — y uno de los más delicados de justificar.</p>
<p><strong>Vídeos 15a y 15b · Ejemplos de debilidades y fortalezas según NEE</strong> Ejemplos reales y completos de cómo describir debilidades y fortalezas para alumnado con integración (AI) y con escolarización (AE).</p>';

upd($pdo, 'temas', 15, $desc_prog_bloque4, $log);
upd($pdo, 'temas', 47, $desc_prog_bloque4, $log);

$desc_prog_bonus = '<p>Como complemento final al curso, tienes acceso a un <strong>ejemplo de programación completa y editable</strong> — para que veas aplicado en un documento real todo lo que has aprendido bloque a bloque, y puedas adaptarlo a tu propio contexto, tu alumnado y tu identidad docente.</p>
<p>Porque este curso no termina aquí. Termina el día que entras al tribunal y demuestras todo lo que vales.</p>';
upd($pdo, 'temas', 26, $desc_prog_bonus, $log);
upd($pdo, 'temas', 58, $desc_prog_bonus, $log);

$desc_prog_gracias = '<p>Quiero que sepas que terminar este curso no es un logro pequeño. Has dedicado tiempo, energía y esfuerzo a construir algo que te representa — una programación con criterio, con identidad y con la huella de la maestra PT que eres.</p>
<p>He puesto en este curso todo lo que sé, todo lo que he aprendido acompañando a cientos de opositoras y todo lo que desearía haber tenido yo cuando empecé. No es un curso perfecto — es un curso honesto. Hecho desde la práctica real, desde el aula y desde el corazón de alguien que cree profundamente en esta especialidad y en las personas que la eligen.</p>
<p>Ahora ve. Revisa. Mejora. Ensaya. Y cuando llegue el momento, entra a ese tribunal sabiendo que has hecho todo lo que estaba en tu mano.</p>
<p><strong>Mucha fuerza, mucho ánimo y muchísima suerte.</strong> Con todo mi cariño, Rocío</p>';
upd($pdo, 'temas', 27, $desc_prog_gracias, $log);
upd($pdo, 'temas', 59, $desc_prog_gracias, $log);


// ════════════════════════════════════════════════════
// TEMAS · Cursos 9 y 13 (Situación de Aprendizaje)
// ════════════════════════════════════════════════════

$sa_bloques = [
    // [tema_id_ordinaria, tema_id_especifica, descripcion]
    [29, 60, '<p><em>Antes de diseñar tu primera Situación de Aprendizaje, necesitas saber exactamente qué estás construyendo, para quién y con qué criterio. Este bloque te da esa base.</em></p>
<p>El error más frecuente al empezar una SA es lanzarse directamente a las actividades sin entender qué evalúa el tribunal, qué es realmente una Situación de Aprendizaje y qué estructura debe tener. Aquí ponemos los cimientos para que todo lo que construyas después tenga solidez, coherencia y sentido pedagógico real.</p>
<p>Porque una SA que no se sabe defender no es una SA de 10. Es una SA a medias.</p>
<p><strong>Vídeo 1 · ¿Qué evalúa realmente el tribunal?</strong> El punto de partida imprescindible. Revisaremos la convocatoria y la rúbrica con detalle para identificar qué busca el tribunal, qué errores debes evitar y qué marca la diferencia entre una SA que aprueba y una que destaca.</p>
<p><strong>Vídeo 2 · ¿Qué es una Situación de Aprendizaje?</strong> Trabajaremos el concepto de SA desde su raíz — con la mirada de Coral Elizondo como referente — y lo conectaremos con el tipo de docente que queremos ser.</p>
<p><strong>Vídeo 3 · Estructura</strong> Las preguntas a las que debe responder toda SA, los tres tipos que existen y cómo elegir el formato según tu contexto y tu alumnado.</p>'],

    [30, 61, '<p><em>Una Situación de Aprendizaje bien diseñada empieza mucho antes de escribir la primera línea. Empieza por organizarte — y ese es exactamente el objetivo de este bloque.</em></p>
<p>Antes de construir una SA necesitas tener claro el nivel educativo, el reparto del contenido curricular a lo largo del año y el lugar que ocupa cada situación de aprendizaje dentro del calendario escolar. Aquí aprenderás a planificar con criterio, a dar título con intención y a fundamentar tu SA desde la normativa y los referentes pedagógicos que el tribunal valora.</p>
<p><strong>Vídeo 4 · Organización</strong> El primer paso real antes de diseñar cualquier SA. Aprenderás a elegir el nivel educativo, a trabajar con el calendario escolar, a repartir las SA en sesiones y a colocar las efemérides con sentido pedagógico.</p>
<p><strong>Vídeo 5 · Título y producto</strong> El título de una SA no es un adorno — es una declaración de intenciones. Aprenderás a construir títulos motivadores y a diseñar un producto final con propósito real conectado con los ODS.</p>
<p><strong>Vídeo 6 · Normativa y fundamentación</strong> Una SA sin fundamentación es una SA frágil. Aquí aprenderás a seleccionar y citar la normativa curricular y específica que sostiene tu intervención.</p>
<p><strong>Vídeo 7 · Primera parte de la SA</strong> Arrancamos la construcción: título, contextualización, temporalización, justificación, objetivos de etapa, planes y programas, efemérides y producto final.</p>'],

    [35, 62, '<p><em>El entramado curricular es donde demuestras que tu SA tiene raíces reales. Este bloque te enseña a construirlo con rigor, con coherencia y con la profundidad que el tribunal espera ver en una maestra PT.</em></p>
<p>Muchas opositoras llegan a este apartado y lo resuelven de forma mecánica — copian criterios, colocan saberes básicos y dan el bloque por cerrado. Pero el tribunal lo nota. Porque un entramado curricular bien construido no es una tabla rellena — es una declaración de intenciones pedagógicas que conecta cada decisión con el aprendizaje real del alumnado.</p>
<p><strong>Vídeo 8 · Entramado curricular</strong> Recordamos qué es el entramado curricular y por qué es uno de los apartados más valorados por el tribunal. Aprenderás a ser competencial de verdad y a trabajar el cuadro curricular diferenciado para alumnado con integración y de aula específica.</p>
<p><strong>Vídeo 9 · Programas específicos</strong> Los programas específicos tienen entidad propia dentro de la SA. Aprenderás qué son, cuáles son los tipos más comunes y cuáles son sus partes esenciales. Idea clave: mejor poco y bien.</p>'],

    [36, 63, '<p><em>La secuencia didáctica es el corazón de tu Situación de Aprendizaje. Aquí es donde el aprendizaje ocurre de verdad — y donde el tribunal comprueba si sabes provocarlo con criterio, con estructura y con una mirada genuinamente inclusiva.</em></p>
<p>Este es el bloque más extenso del curso, y con razón. Una secuencia didáctica bien diseñada no es una lista de actividades ordenadas cronológicamente — es una arquitectura pedagógica con fases, con propósito, con inclusión integrada desde el primer momento y con un producto final que da sentido a cada paso del camino.</p>
<p><strong>Vídeos 10-22:</strong> Estructura de la secuencia · Ejercicios, actividades y tareas · Aplicaciones digitales · Guía de apoyo según NEAE · Rutinas y dinámicas de grupo · Fase de orientación · Fase de motivación · Fase de desarrollo (lecto-escritura, lógico-matemático, aspectos específicos, otras actividades) · Fase de consolidación · Procesos cognitivos.</p>'],

    [37, 64, '<p><em>La metodología no es un apartado que se rellena con nombres bonitos. Es la demostración de que sabes cómo aprende tu alumnado, qué estrategias son las más adecuadas para su perfil y cómo organizas el espacio y los recursos para que el aprendizaje ocurra de verdad.</em></p>
<p>Este bloque es más breve que el anterior, pero no menos importante. El tribunal quiere ver que tienes criterio metodológico propio — que no nombras principios por moda sino porque entiendes qué hay detrás de cada uno y cómo se traduce en la práctica del aula PT.</p>
<p><strong>Vídeo 23 · Principios y técnicas metodológicas</strong> Trabajaremos los principios metodológicos que deben sustentar toda intervención PT con ejemplos concretos de cómo se traducen en decisiones de aula.</p>
<p><strong>Vídeo 24 · Recursos y agrupamientos</strong> Los recursos y los agrupamientos son decisiones pedagógicas — no organizativas. Aprenderás a presentarlos con criterio y coherencia respecto a todo lo que has construido en tu SA.</p>'],

    [38, 65, '<p><em>La atención a la diversidad no es un apartado que se añade al final de la SA para cumplir el expediente. Es una decisión que se toma desde el principio, que impregna cada fase de la secuencia y que demuestra que tu intervención PT es genuinamente inclusiva.</em></p>
<p>Este es uno de los apartados donde más se nota la diferencia entre una opositora que conoce la inclusión de verdad y una que la aplica de forma superficial. El tribunal lo sabe — y aquí aprenderás a construirlo con la profundidad y el criterio que merece.</p>
<p>Porque atención a la diversidad no es poner una actividad más fácil. Es diseñar desde el principio para que todos puedan aprender.</p>
<p><strong>Vídeo 25 · Medidas de atención a la diversidad</strong> Construiremos un cuadro DUA real, no decorativo. Estudiaremos los tres principios del DUA — representación, acción y expresión, e implicación — y cómo aplicarlos con la Ruleta DUA. Las medidas se estructuran en tres momentos: antes de la SA, durante el desarrollo y al finalizar.</p>'],

    [39, 66, '<p><em>Evaluar bien es uno de los actos pedagógicos más complejos y más valorados por el tribunal. Este bloque te enseña a hacerlo con rigor, con variedad de instrumentos y con una mirada que va mucho más allá de poner una nota.</em></p>
<p>La evaluación no cierra la SA — la acompaña desde el principio. Una evaluación bien diseñada es coherente con los criterios de evaluación, con el producto final y con cada decisión metodológica que has tomado a lo largo de la secuencia. Cuando el tribunal ve esa coherencia, lo nota. Y lo valora.</p>
<p><strong>Vídeo 26 · La evaluación</strong> Trabajaremos la evaluación del aprendizaje desde un enfoque formativo real — con feedback genuino y la técnica del sándwich — y veremos cómo estructurarla en tres momentos: inicial, durante y final. Instrumentos: rúbricas, tickets de salida, listas de control, checklists y diana de evaluación. Cierra con la evaluación de la práctica docente y un análisis DAFO.</p>'],

    [40, 67, '<p><em>Estás a punto de terminar tu Situación de Aprendizaje. Este bloque es el broche final — el momento de cerrarla con solidez, de citar con criterio y de aprender a usar la pizarra como la aliada que puede ser en tu defensa oral.</em></p>
<p>Los últimos apartados de una SA son los que muchas opositoras resuelven con prisa porque sienten que lo importante ya está hecho. Error. La conclusión, las referencias y el uso de la pizarra son tres elementos que el tribunal valora — y que bien trabajados elevan el conjunto de tu SA a otro nivel.</p>
<p><strong>Vídeo 27 · Conclusión</strong> La conclusión de una SA no es un resumen — es una síntesis con criterio pedagógico real. Trabajaremos las tres partes que debe tener una buena conclusión.</p>
<p><strong>Vídeo 28 · Referencias</strong> Las referencias no son un listado de relleno — son la demostración de que tu SA tiene fundamento teórico real y actualizado. Veremos las referencias bomba que el tribunal reconoce y valora.</p>
<p><strong>Vídeo 29 · Uso adecuado de la pizarra</strong> La pizarra es una de las herramientas más poderosas de la defensa oral — y una de las menos aprovechadas. Aprenderás a convertirla en una aliada real conectada con tu hilo conductor.</p>'],

    [41, 68, '<p><em>Has construido una Situación de Aprendizaje sólida, coherente y con identidad propia. Ahora toca el momento más importante de todo el proceso — defender lo que has creado con seguridad, con criterio y con la presencia de una maestra PT que sabe exactamente lo que ha hecho y por qué.</em></p>
<p>La defensa oral no es un resumen de tu SA. Es una conversación pedagógica con el tribunal — una oportunidad de demostrar que detrás de ese documento hay una profesional que piensa, que decide y que justifica cada elección con convicción.</p>
<p><strong>Vídeo 30 · Defensa: primera parte</strong> Los primeros minutos de la exposición — los que más impactan. Contextualización, referencias como apoyo argumental, justificación, producto, hilo conductor, entramado curricular. Tiempo orientativo: hasta 10 minutos.</p>
<p><strong>Vídeo 31 · Defensa: segunda parte</strong> Cómo presentar la secuencia didáctica con las cuatro fases bien diferenciadas y los ODS integrados. Tiempo orientativo: entre 10 y 12 minutos.</p>
<p><strong>Vídeo 32 · Defensa: tercera parte</strong> El cierre de la defensa — cómo presentar la evaluación con criterio formativo real, la técnica del sándwich y los instrumentos de evaluación.</p>'],

    [42, 69, '<p>Como complemento final al curso tienes acceso a una <strong>plantilla completa de Situación de Aprendizaje descargable y editable</strong> — con la estructura perfecta, todos los apartados desarrollados y lista para adaptar a tu nivel educativo, tu alumnado y tu estilo docente.</p>
<p>Porque el mejor punto de partida no es una página en blanco — es un modelo de referencia que ya sabes cómo usar.</p>'],

    [43, 70, '<p>Quiero que te detengas un momento antes de cerrar este curso. Piensa en todo lo que has construido. Una Situación de Aprendizaje con título, con producto, con entramado curricular, con secuencia didáctica por fases, con inclusión real, con evaluación coherente y con una defensa preparada para dejar huella en el tribunal. Eso no es poco. Eso es mucho. Y lo has hecho tú.</p>
<p>La Pedagogía Terapéutica no es solo una especialidad — es una forma de mirar la educación. Una forma que pone en el centro a quienes más lo necesitan, que no renuncia a la inclusión aunque sea complicada y que cree profundamente en el potencial de cada alumno y cada alumna. Tú has elegido eso. Y eso ya te hace especial.</p>
<p>Ahora ve. Repasa. Ensaya tu defensa en voz alta. Confía en las decisiones que has tomado. Y cuando llegue el día, entra a ese tribunal sabiendo que detrás de tu SA hay criterio, hay corazón y hay una maestra PT que se ha preparado de verdad.</p>
<p><strong>Mucha fuerza, mucho ánimo y muchísima suerte.</strong> Con todo mi cariño, Rocío</p>'],
];

foreach ($sa_bloques as [$id_ord, $id_esp, $desc]) {
    upd($pdo, 'temas', $id_ord, $desc, $log);
    upd($pdo, 'temas', $id_esp, $desc, $log);
}


// ════════════════════════════════════════════════════
// TEMAS · Curso 14 (Técnicas de preparación)
// ════════════════════════════════════════════════════

// Tema 71 · BLOQUE 0. Punto de partida: conócete
upd($pdo, 'temas', 71, '<p><em>Antes de cambiar cómo estudias, necesitas entender cómo estudias ahora. Este bloque es el espejo — el punto de partida honesto desde el que construir una preparación que realmente funcione.</em></p>
<p>La mayoría de opositoras empiezan a prepararse sin hacerse las preguntas fundamentales: ¿cómo aprendo? ¿Cuánto tiempo tengo de verdad? ¿Qué me funciona y qué me roba energía sin resultados? Aquí aprenderás a responderte — y ese conocimiento lo cambia todo.</p>
<p><strong>Vídeo 1 · ¿Por qué estudiamos mucho y recordamos poco?</strong> Descubrirás la diferencia entre la memoria a corto y a largo plazo, qué es la ilusión de competencia, la curva del olvido y la diferencia fundamental entre estudio pasivo y estudio activo.</p>
<p><strong>Vídeo 2 · Los 4 pilares del éxito</strong> Técnicas de memoria, comprensión conceptual, planificación efectiva y equilibrio emocional: los cuatro pilares que sostienen cualquier preparación exitosa.</p>
<p><strong>Vídeo 3 · ¿Cómo aprendes? Autoanalízate</strong> Cinco dimensiones clave de tu perfil de aprendizaje para tomar decisiones de preparación basadas en tu realidad. Incluye test de autodiagnóstico descargable.</p>
<p><strong>Vídeo 4 · ¿Cuánto tiempo tienes realmente?</strong> Aprenderás a auditarte de verdad — a ver con claridad cuántas horas reales tienes disponibles y cuáles son tus ladrones de tiempo.</p>
<p><strong>Vídeo 5 · Tus fortalezas y puntos de mejora</strong> El método del semáforo y el análisis DAFO como opositora. Incluye DAFO descargable.</p>', $log);

// Tema 72 · BLOQUE 1. Neurociencia aplicada al estudio
upd($pdo, 'temas', 72, '<p><em>Estudiar sin entender cómo funciona tu cerebro es como conducir sin saber las normas de tráfico. Puedes llegar — pero con mucho más riesgo y mucho más esfuerzo del necesario. Este bloque te enseña cómo funciona tu memoria para que puedas aprovecharla al máximo.</em></p>
<p>La neurociencia no es solo para científicos. Es para cualquier opositora que quiera entender por qué algunas cosas se recuerdan y otras se olvidan — y qué hacer al respecto.</p>
<p><strong>Vídeo 6 · ¿Cómo funciona la memoria?</strong> Diferencia entre memoria a largo y corto plazo, las fases para formar recuerdos que duran, el papel del hipocampo y las formas de activación que potencian la retención.</p>
<p><strong>Vídeo 7 · Curva del olvido</strong> La curva del olvido de Ebbinghaus y cómo los repasos estratégicos la modifican a tu favor. Cómo planificar tu preparación para que lo que estudias hoy siga contigo el día del examen.</p>
<p><strong>Vídeo 8 · El papel del sueño en la consolidación</strong> El sueño no es tiempo perdido — es el momento en el que tu cerebro consolida lo que has aprendido. El papel del hipocampo durante la fase REM.</p>
<p><strong>Vídeo 9 · Tu ancla: las emociones</strong> Las emociones son el pegamento de la memoria. Aprenderás a usarlas conscientemente y el método película como herramienta para anclar contenidos complejos.</p>', $log);

// Tema 73 · BLOQUE 2. Técnicas de memoria
upd($pdo, 'temas', 73, '<p><em>Conocer cómo funciona tu cerebro es el primer paso. Saber qué herramientas usar para aprovecharlo es el segundo. Este bloque reúne las técnicas de memoria más potentes y mejor respaldadas por la evidencia — adaptadas a la realidad concreta de la preparación de oposiciones PT.</em></p>
<p>Aquí no encontrarás trucos de memorización vacíos. Encontrarás métodos que funcionan, con ejemplos reales y con orientaciones concretas para integrarlos en tu estudio diario.</p>
<p><strong>Vídeo 10 · Repetición espaciada y flashcards</strong> Una de las técnicas más respaldadas por la neurociencia y más infrautilizadas en la preparación de oposiciones.</p>
<p><strong>Vídeo 11 · El Palacio de la Memoria</strong> Una de las técnicas más antiguas y más poderosas para memorizar grandes volúmenes de información, aplicada al temario PT.</p>
<p><strong>Vídeo 12 · Mnemotecnia</strong> Acrósticos, acrónimos, historias y el método bonsai — con ejemplos reales aplicados al temario PT.</p>
<p><strong>Vídeo 13 · La técnica Feynman y técnicas visuales</strong> Mapas mentales, Visual Thinking y tablas comparativas como herramientas de comprensión profunda.</p>
<p><strong>Vídeo 14 · Otros consejos</strong> La pirámide de aprendizaje de Edgar Dale, bloques de estudio, método de vueltas, subrayado estratégico y las apps más útiles. Incluye tabla visual descargable.</p>', $log);

// Tema 74 · BLOQUE 3. Planificación y gestión del tiempo
upd($pdo, 'temas', 74, '<p><em>Una preparación sin planificación es una preparación a la deriva. Este bloque te enseña a organizar tu tiempo con criterio real — con fases, con herramientas y con la flexibilidad necesaria para que tu planning resista el contacto con la vida.</em></p>
<p>Porque el mejor planning no es el más ambicioso. Es el que se cumple.</p>
<p><strong>Vídeo 15 · Consejos de planificación</strong> Todo lo que necesitas para construir una planificación que funcione de verdad: trabajar con el temario y el calendario, fases de preparación, revisión periódica, barras de dopamina para mantener la motivación y hábitos de estudio sostenibles. Incluye páginas de planificación descargables.</p>', $log);

// Tema 75 · BLOQUE 4. Gestión emocional y ansiedad ante exámenes
upd($pdo, 'temas', 75, '<p><em>Puedes tener el mejor planning del mundo y las técnicas de memoria más potentes — y aun así perder el rendimiento el día del examen si no sabes gestionar los nervios. Este bloque existe para que eso no te pase.</em></p>
<p>La ansiedad ante los exámenes no es una señal de debilidad — es una señal de que te importa. Y se puede gestionar. Con herramientas reales, con práctica y con la mentalidad adecuada para convertir los nervios en energía que trabaje a tu favor.</p>
<p><strong>Vídeo 16 · Consejos de gestión emocional</strong> Qué es la ansiedad ante los exámenes, sus señales de alerta, la reestructuración cognitiva para cambiar los pensamientos que bloquean, la técnica de respiración 4-7-8, el entrenamiento bajo presión, la regla del 1% y las ondas alfa como recurso de activación mental. Incluye carteles descargables con mensajes de apoyo.</p>', $log);


// ════════════════════════════════════════════════════
// TEMAS · Curso 15 (Resolución estratégica de Supuestos)
// ════════════════════════════════════════════════════

// Tema 77 · BLOQUE 1. Conceptos previos
upd($pdo, 'temas', 77, '<p><em>Antes de escribir una sola línea de tu supuesto, necesitas entender qué tienes delante. Este bloque te da las claves para leer con criterio, identificar lo esencial y tomar decisiones pedagógicas fundamentadas desde el primer momento.</em></p>
<p>El error más frecuente en los supuestos prácticos no es no saber suficiente — es no haber comprendido bien el caso antes de empezar a responder. Aquí aprenderás a evitarlo con una lectura estratégica, unos conceptos básicos sólidos y una estructura clara que guiará todo tu desarrollo.</p>
<p><strong>Vídeo 1 · ¿Qué evalúa realmente el tribunal?</strong> Los cuatro elementos clave que determinan toda la respuesta: modalidad de escolarización, tipo de intervención PT, diferencia entre edad y nivel curricular y necesidades prioritarias del alumnado.</p>
<p><strong>Vídeo 2 · Comprender el supuesto antes de escribir</strong> Leer un supuesto no es lo mismo que comprenderlo. Una lectura estratégica marca la diferencia entre un supuesto coherente y uno que pierde puntos desde el principio.</p>
<p><strong>Vídeo 3 · Conceptos básicos</strong> Los conceptos que el tribunal da por supuestos — con casos prácticos reales sobre qué implica "periodos variables" o "aula específica".</p>
<p><strong>Vídeo 4 · La estructura</strong> Los ocho apartados que guían cada resolución: introducción y justificación, fundamentación, contextualización, conceptualización, intervención, evaluación, conclusión y referencias.</p>', $log);

// Tema 78 · BLOQUE 2. Parte inicial
upd($pdo, 'temas', 78, '<p><em>Los primeros apartados de tu supuesto son la primera impresión que recibe el tribunal. Aquí aprenderás a construirlos con solidez, con criterio normativo real y con una mirada holística que demuestra desde el principio que sabes de lo que hablas.</em></p>
<p>La parte inicial no es un trámite burocrático — es la base sobre la que se sostiene todo lo que viene después. Una introducción que puntúa, una contextualización bien construida y una intervención holística bien planteada elevan el conjunto de tu supuesto antes de llegar al desarrollo.</p>
<p><strong>Vídeo 5 · ¿Cómo escribir introducciones que puntúan?</strong> Los elementos clave: la inclusión como marco, la normativa vigente, la atención a la diversidad y el papel de la PT.</p>
<p><strong>Vídeo 6 · Contexto y conceptualización</strong> Identificar las necesidades del alumnado de forma holística y trabajar los perfiles de NEAE y NEE con criterio real.</p>
<p><strong>Vídeo 7 · La intervención holística</strong> La intervención PT ocurre en cuatro niveles que deben aparecer en todo supuesto bien resuelto: centro, aula, individual y contexto.</p>', $log);

// Tema 79 · BLOQUE 3. Parte central
upd($pdo, 'temas', 79, '<p><em>La parte central es el núcleo de tu supuesto — donde demuestras que sabes intervenir de verdad. No solo en el papel, sino con criterio pedagógico real, con conocimiento de la normativa y con una mirada inclusiva que abarca todos los niveles en los que actúa la PT.</em></p>
<p>Este bloque es el más extenso y el más decisivo. Aquí el tribunal comprueba si entiendes tu rol, si conoces las medidas de intervención y si eres capaz de articular una respuesta coherente a nivel de centro, de aula, individual y con el entorno.</p>
<p><strong>Vídeo 8 · Intervención a nivel de centro</strong> Acciones de información y sensibilización, coordinación y formación docente, planes y programas de centro, recreos inclusivos y detección temprana.</p>
<p><strong>Vídeo 9 · Intervención a nivel de aula</strong> La PT como motor de la inclusión: colaboración con el equipo docente, entramado curricular, estructura inclusiva y metodologías activas — ABP, aprendizaje cooperativo y DUA.</p>
<p><strong>Vídeo 10 · Intervención individual con el alumnado</strong> Las tres medidas fundamentales — PE, ACS y ACI — con sus características diferenciales y sus opciones de desarrollo.</p>
<p><strong>Vídeo 11 · Intervención con el entorno</strong> La coordinación y colaboración con familia, equipo docente y otros profesionales.</p>', $log);

// Tema 80 · BLOQUE 4. Parte final
upd($pdo, 'temas', 80, '<p><em>El cierre de un supuesto es tan importante como su apertura. Este bloque te enseña a evaluar con rigor, a concluir con impacto y a referenciar con criterio — los tres elementos que cierran el círculo pedagógico y dejan al tribunal con la sensación de haber leído un supuesto completo, coherente y profesional.</em></p>
<p>Muchas opositoras llegan a este punto con prisa y resuelven la parte final de forma apresurada. Y es precisamente aquí donde se pueden ganar o perder puntos decisivos.</p>
<p><strong>Vídeo 12 · Evaluación del aprendizaje</strong> El grado de desarrollo de los aprendizajes, los principios evaluadores, los criterios de evaluación adaptados y los tres momentos clave: inicial, continua y final. Con análisis DAFO.</p>
<p><strong>Vídeo 13 · Evaluación de la práctica docente</strong> Ajustar la intervención a partir de la reflexión sobre la propia práctica — con indicadores, análisis DAFO y propuestas de mejora.</p>
<p><strong>Vídeo 14 · Conclusión</strong> La última oportunidad de dejar huella. Qué busca el tribunal, qué extensión es adecuada y cómo cerrar con coherencia y emoción pedagógica.</p>
<p><strong>Vídeo 15 · Referencias</strong> Los tres tipos — normativa, bibliográfica y webgráfica — con criterio. Incluye checklist descargable.</p>', $log);

// Tema 81 · BLOQUE 5. Estrategia para el examen
upd($pdo, 'temas', 81, '<p><em>Saber resolver un supuesto y saber resolverlo el día del examen no es lo mismo. Este bloque te prepara para ese momento — con estrategia, con criterio de revisión y con la cabeza fría que necesitas para dar lo mejor de ti cuando más importa.</em></p>
<p>La técnica sin estrategia se queda a medias. Aquí aprenderás a gestionar el tiempo, a extraer la información clave bajo presión, a revisar tu respuesta con tres niveles de análisis y a llegar al examen con un método claro y probado.</p>
<p><strong>Vídeo 16 · Supuestos prácticos de 10</strong> Gestión del tiempo, extracción de información clave del enunciado, cronometrado durante la preparación y estructura recomendada. Incluye supuesto de 10 descargable.</p>
<p><strong>Vídeo 17 · ¿Cómo revisar tu supuesto?</strong> La revisión en tres niveles: estructura, técnica y estrategia. Incluye herramienta de autoevaluación descargable.</p>', $log);

// Tema 82 · BLOQUE 6. Ponte a prueba
upd($pdo, 'temas', 82, '<p><em>El conocimiento sin práctica no es suficiente. Este bloque es donde todo lo aprendido se convierte en habilidad real — con supuestos completos, resueltos y comentados para las tipologías de NEE más frecuentes en las oposiciones a PT.</em></p>
<p>Leer supuestos resueltos no es copiar — es aprender a pensar. Cada supuesto de este bloque te muestra cómo se aplica la estructura, cómo se toman las decisiones pedagógicas y cómo se articula una respuesta coherente ante un perfil de alumnado concreto.</p>
<p><strong>Vídeo 18 · Antes de hacer simulacros</strong> Las tres opciones de supuesto (A, B y C) con sus características propias.</p>
<p><strong>Vídeos 19-23 · Supuestos resueltos por tipología:</strong> Discapacidad Auditiva · Discapacidad Visual · Discapacidad Intelectual · Discapacidad Motórica · TEA. Cada uno con propuesta completa, decisiones pedagógicas justificadas y supuesto descargable.</p>', $log);

// Tema 83 · BONUS Supuestos
upd($pdo, 'temas', 83, '<p>Como complemento final al curso tienes acceso a <strong>tres resoluciones completas y descargables</strong> que amplían tu repertorio de respuesta para los documentos más técnicos y más valorados de la intervención PT:</p>
<ul>
<li><strong>Bonus 1 · Resolución completa de PE</strong> — Programa Específico desarrollado con todos sus apartados.</li>
<li><strong>Bonus 2 · Resolución completa de ACS</strong> — Adaptación Curricular Significativa resuelta con criterio real.</li>
<li><strong>Bonus 3 · Resolución completa de ACI</strong> — Adaptación Curricular Individual desarrollada paso a paso.</li>
</ul>
<p>Porque llegar al examen habiendo visto cómo se resuelve de verdad no tiene precio.</p>', $log);

// Tema 84 · Gracias Supuestos
upd($pdo, 'temas', 84, '<p>Para un momento. Respira. Piensa en todo lo que has recorrido desde el primer vídeo hasta este momento. Has aprendido a leer un supuesto con criterio, a comprenderlo antes de escribir, a construir cada apartado con coherencia y a cerrar con la solidez que el tribunal espera. Has practicado con cinco tipologías de NEE diferentes. Has aprendido a revisar, a ajustar y a gestionar el tiempo bajo presión.</p>
<p>Eso no es poca cosa. Eso es preparación real.</p>
<p>Porque resolver un supuesto práctico no es solo demostrar que sabes. Es demostrar que piensas como una maestra PT. Que ves al alumno detrás del caso. Que entiendes la inclusión no como un concepto sino como una forma de actuar. Y eso — eso sí lo tienes tú.</p>
<p>Ahora solo queda una cosa: practicar, confiar y entrar a ese examen sabiendo que tienes un método, tienes criterio y tienes todo lo necesario para resolverlo con la cabeza fría y el corazón tranquilo.</p>
<p><strong>Mucha fuerza, mucho ánimo y muchísima suerte.</strong> Con todo mi cariño, Rocío</p>', $log);


// ════════════════════════════════════════════════════
echo json_encode(['ok' => true, 'total' => count($log), 'resultados' => $log], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
