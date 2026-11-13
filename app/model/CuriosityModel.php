<?php

namespace app\model;

class CuriosityModel
{
    private const FACTS = [
        ['texto' => 'La luz del Sol tarda unos ocho minutos en llegar a la Tierra.', 'categoria' => 'Ciencia'],
        ['texto' => 'Los pulpos tienen tres corazones.', 'categoria' => 'Ciencia'],
        ['texto' => 'La Antártida es el continente más frío de la Tierra.', 'categoria' => 'Geografía'],
        ['texto' => 'Un triatlón combina natación, ciclismo y carrera a pie.', 'categoria' => 'Deporte'],
        ['texto' => 'El ajedrez tiene 64 casillas en su tablero.', 'categoria' => 'Deporte'],
        ['texto' => 'Leonardo da Vinci pintó la Mona Lisa.', 'categoria' => 'Arte'],
        ['texto' => 'El guion de una película incluye los diálogos y las acciones de sus personajes.', 'categoria' => 'Cine'],
        ['texto' => 'La escritura cuneiforme se desarrolló en la antigua Mesopotamia.', 'categoria' => 'Historia'],
        ['texto' => 'Venus tarda más en girar sobre su eje que en dar una vuelta alrededor del Sol.', 'categoria' => 'Ciencia'],
        ['texto' => 'El océano Pacífico es el océano más extenso del planeta.', 'categoria' => 'Geografía'],
        ['texto' => 'Los primeros Juegos Olímpicos modernos se celebraron en Atenas en 1896.', 'categoria' => 'Historia'],
        ['texto' => 'En una orquesta, el oboe suele dar la nota de referencia para afinar.', 'categoria' => 'Arte'],

        // Ciencia
        ['texto' => 'Los wombats hacen caca con forma de cubo. La naturaleza también juega al Minecraft.', 'categoria' => 'Ciencia'],
        ['texto' => 'Los flamencos deben su color rosado a pigmentos de su alimentación. Su look viene incluido en el menú.', 'categoria' => 'Ciencia'],
        ['texto' => 'Las mariposas tienen receptores del gusto en las patas. Para ellas, pisar la comida puede ser una cata.', 'categoria' => 'Ciencia'],
        ['texto' => 'El ornitorrinco es un mamífero que pone huevos. A veces la biología mezcla todos los modos de juego.', 'categoria' => 'Ciencia'],
        ['texto' => 'Los tiburones existían antes que los primeros árboles. Llevan muchísimo tiempo sin necesitar sombra.', 'categoria' => 'Ciencia'],
        ['texto' => 'Los caracoles tienen una rádula cubierta de diminutos dientes con la que raspan su comida.', 'categoria' => 'Ciencia'],
        ['texto' => 'Los caballitos de mar machos incuban los huevos en una bolsa y dan a luz a las crías.', 'categoria' => 'Ciencia'],
        ['texto' => 'Las nutrias marinas utilizan piedras para abrir algunos de los animales con concha que comen.', 'categoria' => 'Ciencia'],
        ['texto' => 'Las abejas melíferas pueden comunicar la ubicación de alimento mediante una danza. El GPS les salió coreográfico.', 'categoria' => 'Ciencia'],
        ['texto' => 'En el vacío, el sonido no se propaga: necesita un medio como el aire. En el espacio, tu vecino no podría escuchar tu karaoke.', 'categoria' => 'Ciencia'],
        ['texto' => 'La luz de la Luna es principalmente luz solar reflejada. Tiene iluminación prestada y aun así sale bien en las fotos.', 'categoria' => 'Ciencia'],
        ['texto' => 'Urano gira con el eje tan inclinado que parece rodar de costado alrededor del Sol.', 'categoria' => 'Ciencia'],
        ['texto' => 'Un año de Neptuno dura unos 165 años terrestres. Esperar al próximo cumpleaños sería un proyecto serio.', 'categoria' => 'Ciencia'],
        ['texto' => 'En Marte, el monte Olimpo se eleva unos 22 kilómetros sobre el nivel de referencia del planeta.', 'categoria' => 'Ciencia'],
        ['texto' => 'El hielo flota porque es menos denso que el agua líquida. Por eso los lagos empiezan a congelarse por arriba.', 'categoria' => 'Ciencia'],
        ['texto' => 'Botánicamente, la banana es una baya, pero la frutilla no. La ensalada de frutas tiene problemas de identidad.', 'categoria' => 'Ciencia'],
        ['texto' => 'Los huesos del oído medio se llaman martillo, yunque y estribo. Tenés una pequeña ferretería en la cabeza.', 'categoria' => 'Ciencia'],
        ['texto' => 'Las plantas producen azúcares usando luz, agua y dióxido de carbono. Cocinan con energía solar desde antes de que estuviera de moda.', 'categoria' => 'Ciencia'],

        // Geografía
        ['texto' => 'La Antártida es un desierto: recibe muy pocas precipitaciones. Un desierto también puede necesitar bufanda.', 'categoria' => 'Geografía'],
        ['texto' => 'África tiene territorio en los cuatro hemisferios: la cruzan tanto el ecuador como el meridiano de Greenwich.', 'categoria' => 'Geografía'],
        ['texto' => 'Nepal tiene una bandera nacional formada por dos triángulos superpuestos. No quiso entrar en el club de los rectángulos.', 'categoria' => 'Geografía'],
        ['texto' => 'Lesoto está completamente rodeado por Sudáfrica. Para salir por tierra, siempre hay que pasar por el mismo vecino.', 'categoria' => 'Geografía'],
        ['texto' => 'El Vaticano está dentro de la ciudad de Roma. Podés cambiar de país sin cambiar de ciudad.', 'categoria' => 'Geografía'],
        ['texto' => 'El mar Caspio es un gran cuerpo de agua interior sin salida natural al océano. El nombre no siempre cuenta toda la historia.', 'categoria' => 'Geografía'],
        ['texto' => 'El lago Baikal, en Siberia, es el lago más profundo del mundo: supera los 1.600 metros de profundidad.', 'categoria' => 'Geografía'],
        ['texto' => 'El punto Nemo es el punto del océano más alejado de tierra firme. No es el mejor lugar para pedir una pizza.', 'categoria' => 'Geografía'],
        ['texto' => 'La cordillera de los Andes recorre el oeste de Sudamérica a lo largo de unos 7.000 kilómetros.', 'categoria' => 'Geografía'],
        ['texto' => 'El Aconcagua, en Mendoza, es la montaña más alta fuera de Asia. Argentina también compite en altura.', 'categoria' => 'Geografía'],
        ['texto' => 'El Amazonas desemboca en el Atlántico y transporta más agua que cualquier otro río del planeta.', 'categoria' => 'Geografía'],
        ['texto' => 'Islandia se encuentra sobre la dorsal mesoatlántica, donde se separan las placas norteamericana y euroasiática.', 'categoria' => 'Geografía'],
        ['texto' => 'El Kilimanjaro está cerca del ecuador, pero tiene glaciares en su cumbre. La altura cambia por completo el clima.', 'categoria' => 'Geografía'],
        ['texto' => 'El canal de Panamá conecta el Atlántico y el Pacífico mediante esclusas que elevan y bajan los barcos.', 'categoria' => 'Geografía'],
        ['texto' => 'El estrecho de Bering separa Asia de América del Norte. Rusia y Alaska están mucho más cerca de lo que sugiere un mapamundi.', 'categoria' => 'Geografía'],
        ['texto' => 'El salar de Uyuni, en Bolivia, puede parecer un espejo gigante cuando una fina capa de agua cubre su superficie.', 'categoria' => 'Geografía'],
        ['texto' => 'La isla de Madagascar se separó de la India hace millones de años, aunque hoy esté junto a África.', 'categoria' => 'Geografía'],
        ['texto' => 'El Sahara no fue siempre tan seco: tuvo períodos con lagos, vegetación y fauna propia de paisajes mucho más húmedos.', 'categoria' => 'Geografía'],

        // Historia
        ['texto' => 'Cleopatra vivió más cerca en el tiempo de la llegada a la Luna que de la construcción de la Gran Pirámide de Guiza.', 'categoria' => 'Historia'],
        ['texto' => 'La Universidad de Oxford ya tenía actividad docente en 1096, siglos antes de la formación del Imperio azteca.', 'categoria' => 'Historia'],
        ['texto' => 'En 1932, Australia empleó soldados contra emúes que dañaban cultivos. El episodio quedó conocido como la Guerra del Emú.', 'categoria' => 'Historia'],
        ['texto' => 'En 1969, el Apolo 11 llevó seres humanos a la Luna. La primera caminata lunar duró poco más de dos horas y media.', 'categoria' => 'Historia'],
        ['texto' => 'La piedra de Rosetta presenta un mismo decreto en tres escrituras y fue clave para descifrar los jeroglíficos egipcios.', 'categoria' => 'Historia'],
        ['texto' => 'La imprenta de Gutenberg utilizaba tipos móviles de metal. Reordenar letras cambió la forma de compartir ideas.', 'categoria' => 'Historia'],
        ['texto' => 'En 1582, los países que adoptaron de inmediato el calendario gregoriano pasaron del 4 al 15 de octubre. Ese mes vino con días salteados.', 'categoria' => 'Historia'],
        ['texto' => 'La Torre Eiffel se construyó para la Exposición Universal de París de 1889. Lo temporal a veces se queda para siempre.', 'categoria' => 'Historia'],
        ['texto' => 'La Estatua de la Libertad fue un regalo de Francia a Estados Unidos y se inauguró en 1886.', 'categoria' => 'Historia'],
        ['texto' => 'El Titanic se hundió en 1912 durante su viaje inaugural. Había partido de Southampton rumbo a Nueva York.', 'categoria' => 'Historia'],
        ['texto' => 'La primera vuelta al mundo la completó en 1522 la expedición que inició Magallanes y terminó al mando de Elcano.', 'categoria' => 'Historia'],
        ['texto' => 'El primer viaje del subte de Buenos Aires se realizó en 1913. Fue el primer ferrocarril subterráneo de América Latina.', 'categoria' => 'Historia'],
        ['texto' => 'La declaración de independencia argentina se firmó en Tucumán en 1816, seis años después de la Revolución de Mayo.', 'categoria' => 'Historia'],
        ['texto' => 'El cruce de los Andes de 1817 utilizó varias rutas para dividir las fuerzas y sorprender al ejército realista.', 'categoria' => 'Historia'],
        ['texto' => 'Los antiguos romanos construyeron acueductos que transportaban agua aprovechando la gravedad, sin bombas eléctricas.', 'categoria' => 'Historia'],
        ['texto' => 'Los incas utilizaron quipus, conjuntos de cuerdas con nudos, para registrar información. Sus cuentas sí podían hacerse un nudo.', 'categoria' => 'Historia'],
        ['texto' => 'La expedición de Lewis y Clark atravesó parte de Norteamérica entre 1804 y 1806, con ayuda de pueblos indígenas.', 'categoria' => 'Historia'],
        ['texto' => 'La caída del Muro de Berlín ocurrió en noviembre de 1989; la reunificación alemana se concretó en octubre de 1990.', 'categoria' => 'Historia'],

        // Deporte
        ['texto' => 'En 1966, un perro llamado Pickles encontró el trofeo del Mundial que había sido robado en Londres. Buen olfato para el fútbol.', 'categoria' => 'Deporte'],
        ['texto' => 'Abebe Bikila ganó la maratón olímpica de Roma 1960 corriendo descalzo. Las zapatillas no tuvieron cómo llevarse el mérito.', 'categoria' => 'Deporte'],
        ['texto' => 'Dick Fosbury ganó el salto en alto en México 1968 pasando la barra de espaldas y ayudó a popularizar esa técnica.', 'categoria' => 'Deporte'],
        ['texto' => 'El primer Mundial masculino de fútbol se jugó en Uruguay en 1930, y el anfitrión salió campeón.', 'categoria' => 'Deporte'],
        ['texto' => 'El Mundial de fútbol de 1950 se resolvió con una liguilla final, no con una final eliminatoria. Uruguay ganó el partido decisivo ante Brasil.', 'categoria' => 'Deporte'],
        ['texto' => 'Argentina ganó su primer Mundial masculino de fútbol en 1978 y el segundo en 1986, con Diego Maradona como capitán.', 'categoria' => 'Deporte'],
        ['texto' => 'La selección argentina campeona del Mundial de 2022 había perdido su primer partido del torneo ante Arabia Saudita.', 'categoria' => 'Deporte'],
        ['texto' => 'En París 1900 hubo una prueba olímpica de natación con obstáculos. Nadar rápido no era suficiente: también había que trepar.', 'categoria' => 'Deporte'],
        ['texto' => 'El tira y afloja fue un deporte olímpico entre 1900 y 1920. Tirar de la soga llegó a valer medallas.', 'categoria' => 'Deporte'],
        ['texto' => 'Los primeros Juegos Olímpicos de Invierno se celebraron en Chamonix, Francia, en 1924.', 'categoria' => 'Deporte'],
        ['texto' => 'La primera maratón olímpica femenina se disputó en Los Ángeles 1984 y la ganó Joan Benoit.', 'categoria' => 'Deporte'],
        ['texto' => 'Jamaica debutó en el bobsleigh olímpico en Calgary 1988. Tener un país tropical no impidió competir sobre hielo.', 'categoria' => 'Deporte'],
        ['texto' => 'El equipo estadounidense de básquet conocido como Dream Team ganó el oro en Barcelona 1992 con figuras como Jordan, Magic y Bird.', 'categoria' => 'Deporte'],
        ['texto' => 'Spyridon Louis ganó la primera maratón de los Juegos Olímpicos modernos, disputada en Atenas en 1896.', 'categoria' => 'Deporte'],
        ['texto' => 'El básquet nació en 1891, cuando James Naismith diseñó un juego para practicar bajo techo durante el invierno.', 'categoria' => 'Deporte'],
        ['texto' => 'Las primeras canastas de básquet eran cestas para duraznos. Al principio había que recuperar la pelota después de encestar.', 'categoria' => 'Deporte'],
        ['texto' => 'En Seúl 1988, el tenis volvió al programa olímpico de medallas después de una ausencia de más de seis décadas.', 'categoria' => 'Deporte'],
        ['texto' => 'En los Juegos de Londres 1908, la maratón tuvo 42,195 kilómetros: esa distancia se adoptó como estándar en 1921.', 'categoria' => 'Deporte'],

        // Arte
        ['texto' => 'Marcel Duchamp presentó un urinario como obra de arte en 1917 y lo llamó Fuente. El museo nunca volvió a mirar igual al baño.', 'categoria' => 'Arte'],
        ['texto' => 'La Mona Lisa fue robada del Louvre en 1911 y recuperada en 1913. Hasta los cuadros famosos pueden desaparecer de gira.', 'categoria' => 'Arte'],
        ['texto' => 'Miguel Ángel pintó la bóveda de la Capilla Sixtina entre 1508 y 1512, trabajando sobre andamios.', 'categoria' => 'Arte'],
        ['texto' => 'El David de Miguel Ángel supera los cinco metros de altura. No es una estatua que entre fácilmente en el living.', 'categoria' => 'Arte'],
        ['texto' => 'La noche estrellada de Van Gogh fue pintada en 1889, durante su estancia en Saint-Rémy-de-Provence.', 'categoria' => 'Arte'],
        ['texto' => 'Los relojes blandos de Salvador Dalí aparecen en La persistencia de la memoria, un cuadro de 1931. Puntualidad surrealista.', 'categoria' => 'Arte'],
        ['texto' => 'El Guernica de Picasso está pintado en blanco, negro y grises, sin los colores vivos de muchas de sus otras obras.', 'categoria' => 'Arte'],
        ['texto' => 'La gran ola de Kanagawa, de Hokusai, es una estampa realizada mediante grabado en madera, no una pintura al óleo.', 'categoria' => 'Arte'],
        ['texto' => 'En Las meninas, Velázquez se pintó a sí mismo trabajando ante un gran lienzo. Un autorretrato dentro de otra escena.', 'categoria' => 'Arte'],
        ['texto' => 'El retrato de los Arnolfini, de Van Eyck, incluye un espejo diminuto que refleja a los protagonistas y otras dos figuras.', 'categoria' => 'Arte'],
        ['texto' => 'El grito de Edvard Munch existe en varias versiones realizadas con distintas técnicas. Una sola crisis no le alcanzó al cuadro.', 'categoria' => 'Arte'],
        ['texto' => 'La técnica del puntillismo construye imágenes con pequeños puntos de color. La paciencia también puede ser una herramienta de pintura.', 'categoria' => 'Arte'],
        ['texto' => 'Un fresco se pinta sobre un revoque todavía húmedo. La pared y el artista tienen que coordinar sus tiempos.', 'categoria' => 'Arte'],
        ['texto' => 'El piano produce sonido mediante martillos que golpean cuerdas. Por dentro es mucho más mecánico de lo que parece.', 'categoria' => 'Arte'],
        ['texto' => 'El saxofón suele estar hecho de metal, pero pertenece a la familia de viento madera por su lengüeta y forma de producir sonido.', 'categoria' => 'Arte'],
        ['texto' => 'Beethoven siguió componiendo mientras perdía la audición; la Novena sinfonía pertenece a sus últimos años.', 'categoria' => 'Arte'],
        ['texto' => 'El ballet El cascanueces, con música de Chaikovski, se estrenó en San Petersburgo en 1892.', 'categoria' => 'Arte'],
        ['texto' => 'El bandoneón, tan ligado al tango rioplatense, nació en Alemania. A veces el instrumento también tiene historia de inmigrante.', 'categoria' => 'Arte'],

        // Cine
        ['texto' => 'Toy Story, estrenada en 1995, fue el primer largometraje realizado íntegramente con animación por computadora.', 'categoria' => 'Cine'],
        ['texto' => 'En El mago de Oz de 1939, Kansas aparece en tonos sepia y el mundo de Oz en color. El cambio de escenario también cambia la paleta.', 'categoria' => 'Cine'],
        ['texto' => 'El viaje a la Luna, de Georges Méliès, ya mostraba una aventura espacial en 1902, décadas antes de los viajes reales.', 'categoria' => 'Cine'],
        ['texto' => 'La película argentina El secreto de sus ojos ganó el Óscar a mejor película extranjera en 2010.', 'categoria' => 'Cine'],
        ['texto' => 'La historia oficial fue la primera película argentina en ganar el Óscar a mejor película extranjera, en 1986.', 'categoria' => 'Cine'],
        ['texto' => 'Titanic, de James Cameron, combina una historia de amor ficticia con el hundimiento de un barco que sí existió.', 'categoria' => 'Cine'],
        ['texto' => 'La primera película estrenada de Star Wars salió en 1977; hoy se la conoce como Episodio IV. La saga empezó por el medio.', 'categoria' => 'Cine'],
        ['texto' => 'Por Blancanieves y los siete enanitos, Walt Disney recibió un Óscar honorífico acompañado por siete estatuillas pequeñas. El premio vino en familia.', 'categoria' => 'Cine'],
        ['texto' => 'El señor de los anillos se rodó en Nueva Zelanda, que se convirtió en la Tierra Media de la trilogía.', 'categoria' => 'Cine'],
        ['texto' => 'Jurassic Park, de 1993, combinó dinosaurios animatrónicos con efectos digitales. No todos los rugidos salieron de una computadora.', 'categoria' => 'Cine'],
        ['texto' => 'King Kong, de 1933, dio vida a su gorila gigante mediante animación cuadro por cuadro con modelos en miniatura.', 'categoria' => 'Cine'],
        ['texto' => 'El extraño mundo de Jack está realizado con stop motion: personajes físicos fotografiados después de cada pequeño movimiento.', 'categoria' => 'Cine'],
        ['texto' => 'En cine, los artistas de foley recrean sonidos cotidianos para acompañar las imágenes. Un paso dramático puede haber nacido en un estudio.', 'categoria' => 'Cine'],
        ['texto' => 'La claqueta ayuda a sincronizar imagen y sonido: el golpe puede verse en la imagen y reconocerse en la grabación.', 'categoria' => 'Cine'],
        ['texto' => 'El croma permite sustituir un fondo de color uniforme por otro escenario. Viajar por la galaxia puede empezar frente a una pared verde.', 'categoria' => 'Cine'],
        ['texto' => 'La animación tradicional crea movimiento al mostrar dibujos sucesivos. Cada gesto que parece fácil puede esconder montones de dibujos.', 'categoria' => 'Cine'],
        ['texto' => 'El cine mudo solía proyectarse con acompañamiento musical en vivo. Que nadie hablara en la película no significaba silencio en la sala.', 'categoria' => 'Cine'],
        ['texto' => 'Una escena puede filmarse muchas veces desde distintos ángulos y unirse después en el montaje. El mismo café puede durar toda una jornada.', 'categoria' => 'Cine'],
    ];

    public function next()
    {
        $previous = $_SESSION['curiosity_index'] ?? null;
        $index = random_int(0, count(self::FACTS) - 1);
        if ($index === $previous) {
            $index = ($index + 1) % count(self::FACTS);
        }
        $_SESSION['curiosity_index'] = $index;
        $fact = self::FACTS[$index];
        $colors = [
            'Ciencia' => 'ciencia', 'Geografía' => 'geografia',
            'Deporte' => 'deporte', 'Arte' => 'arte',
            'Cine' => 'cine', 'Historia' => 'historia'
        ];
        $fact['color'] = $colors[$fact['categoria']];
        return $fact;
    }
}
