-- 180 preguntas nuevas: 30 por categoría, con cuatro opciones y una correcta.
-- Seleccionar la base del proyecto antes de importar. Compatible con phpMyAdmin.
-- Puede repetirse: omite preguntas existentes y conserva sus respuestas.
SET NAMES utf8mb4;

CREATE TEMPORARY TABLE carga_preguntas_extra (
    categoria VARCHAR(100) NOT NULL,
    pregunta VARCHAR(500) NOT NULL,
    correcta VARCHAR(255) NOT NULL,
    incorrecta1 VARCHAR(255) NOT NULL,
    incorrecta2 VARCHAR(255) NOT NULL,
    incorrecta3 VARCHAR(255) NOT NULL,
    existente_id INT DEFAULT NULL
) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

INSERT INTO carga_preguntas_extra
    (categoria, pregunta, correcta, incorrecta1, incorrecta2, incorrecta3)
VALUES
-- Geografía
('Geografía', '¿Cuál es la capital de Francia?', 'París', 'Lyon', 'Marsella', 'Burdeos'),
('Geografía', '¿Cuál es la capital de Italia?', 'Roma', 'Milán', 'Venecia', 'Florencia'),
('Geografía', '¿Cuál es la capital de España?', 'Madrid', 'Barcelona', 'Sevilla', 'Valencia'),
('Geografía', '¿Cuál es la capital de Uruguay?', 'Montevideo', 'Salto', 'Colonia del Sacramento', 'Punta del Este'),
('Geografía', '¿Cuál es la capital de Chile?', 'Santiago', 'Valparaíso', 'Concepción', 'Antofagasta'),
('Geografía', '¿Cuál es la capital de Perú?', 'Lima', 'Cusco', 'Arequipa', 'Trujillo'),
('Geografía', '¿Cuál es la capital de Colombia?', 'Bogotá', 'Medellín', 'Cali', 'Cartagena'),
('Geografía', '¿Cuál es la capital de Brasil?', 'Brasilia', 'Río de Janeiro', 'San Pablo', 'Salvador'),
('Geografía', '¿Cuál es la capital de Canadá?', 'Ottawa', 'Toronto', 'Vancouver', 'Montreal'),
('Geografía', '¿Cuál es la capital de Australia?', 'Canberra', 'Sídney', 'Melbourne', 'Brisbane'),
('Geografía', '¿Cuál es la capital de Alemania?', 'Berlín', 'Múnich', 'Hamburgo', 'Fráncfort'),
('Geografía', '¿Cuál es la capital de Portugal?', 'Lisboa', 'Oporto', 'Coímbra', 'Braga'),
('Geografía', '¿En qué país está la Torre de Pisa?', 'Italia', 'Francia', 'España', 'Grecia'),
('Geografía', '¿En qué país está la ciudad de Marrakech?', 'Marruecos', 'Egipto', 'Túnez', 'Argelia'),
('Geografía', '¿Qué cordillera recorre el oeste de Sudamérica?', 'Los Andes', 'Los Alpes', 'Los Pirineos', 'Los Urales'),
('Geografía', '¿Cuál es la montaña más alta del mundo sobre el nivel del mar?', 'Everest', 'Aconcagua', 'Kilimanjaro', 'Mont Blanc'),
('Geografía', '¿Qué océano está entre América y Europa?', 'Atlántico', 'Pacífico', 'Índico', 'Ártico'),
('Geografía', '¿En qué continente se encuentra Kenia?', 'África', 'Asia', 'Europa', 'Oceanía'),
('Geografía', '¿En qué continente se encuentra Tailandia?', 'Asia', 'África', 'Europa', 'América'),
('Geografía', '¿Qué país tiene forma de bota en los mapas?', 'Italia', 'Noruega', 'Portugal', 'Irlanda'),
('Geografía', '¿Qué río atraviesa la ciudad de Londres?', 'Támesis', 'Sena', 'Danubio', 'Rin'),
('Geografía', '¿Qué río atraviesa París?', 'Sena', 'Támesis', 'Tíber', 'Volga'),
('Geografía', '¿Qué mar separa el sur de Europa del norte de África?', 'Mediterráneo', 'Báltico', 'Caribe', 'Mar del Norte'),
('Geografía', '¿En qué país se encuentra la mayor parte del desierto de Atacama?', 'Chile', 'Argentina', 'Brasil', 'Uruguay'),
('Geografía', '¿En qué provincia argentina está la ciudad de Bariloche?', 'Río Negro', 'Mendoza', 'Neuquén', 'Chubut'),
('Geografía', '¿En qué provincia argentina están las Cataratas del Iguazú?', 'Misiones', 'Corrientes', 'Entre Ríos', 'Formosa'),
('Geografía', '¿Qué país limita con Argentina al oeste?', 'Chile', 'Ecuador', 'Colombia', 'Venezuela'),
('Geografía', '¿Cuál es el país más extenso del mundo?', 'Rusia', 'Canadá', 'China', 'Estados Unidos'),
('Geografía', '¿Qué país está al sur de Estados Unidos y al norte de Guatemala?', 'México', 'Panamá', 'Costa Rica', 'Honduras'),
('Geografía', '¿Qué isla comparten Haití y República Dominicana?', 'La Española', 'Cuba', 'Jamaica', 'Puerto Rico'),
-- Historia
('Historia', '¿En qué año comenzó la Primera Guerra Mundial?', '1914', '1918', '1939', '1905'),
('Historia', '¿En qué año terminó la Primera Guerra Mundial?', '1918', '1914', '1945', '1929'),
('Historia', '¿En qué año comenzó la Segunda Guerra Mundial?', '1939', '1941', '1914', '1945'),
('Historia', '¿En qué año cayó el Muro de Berlín?', '1989', '1979', '1999', '1961'),
('Historia', '¿En qué año comenzó la Revolución francesa?', '1789', '1816', '1776', '1848'),
('Historia', '¿En qué año llegó Cristóbal Colón a América por primera vez?', '1492', '1453', '1519', '1600'),
('Historia', '¿Qué civilización construyó las pirámides de Guiza?', 'La egipcia', 'La romana', 'La inca', 'La azteca'),
('Historia', '¿Quién fue el primer emperador romano?', 'Augusto', 'Nerón', 'Trajano', 'Constantino'),
('Historia', '¿En qué ciudad de la Antigüedad surgió una democracia directa célebre?', 'Atenas', 'Esparta', 'Roma', 'Cartago'),
('Historia', '¿Qué pueblo fundó la ciudad de Tenochtitlan?', 'Los mexicas', 'Los incas', 'Los romanos', 'Los fenicios'),
('Historia', '¿Qué ciudad fue la capital del Imperio inca?', 'Cusco', 'Lima', 'Quito', 'Bogotá'),
('Historia', '¿Qué navegante completó la primera vuelta al mundo al mando de la expedición?', 'Juan Sebastián Elcano', 'Cristóbal Colón', 'Vasco da Gama', 'Américo Vespucio'),
('Historia', '¿Quién creó la bandera argentina?', 'Manuel Belgrano', 'José de San Martín', 'Domingo Faustino Sarmiento', 'Juan Bautista Alberdi'),
('Historia', '¿En qué ciudad se declaró la independencia argentina en 1816?', 'San Miguel de Tucumán', 'Buenos Aires', 'Córdoba', 'Salta'),
('Historia', '¿Qué fecha se conmemora por la Revolución de Mayo argentina?', '25 de mayo de 1810', '9 de julio de 1816', '20 de junio de 1820', '17 de agosto de 1850'),
('Historia', '¿Quién presidió la Primera Junta de gobierno de 1810?', 'Cornelio Saavedra', 'Mariano Moreno', 'Manuel Belgrano', 'Juan José Castelli'),
('Historia', '¿En qué año se sancionó la Constitución argentina que dio origen al texto vigente?', '1853', '1810', '1816', '1912'),
('Historia', '¿Qué general fue conocido como el Libertador en gran parte del norte de Sudamérica?', 'Simón Bolívar', 'Napoleón Bonaparte', 'Julio César', 'George Washington'),
('Historia', '¿Quién fue el primer presidente de Estados Unidos?', 'George Washington', 'Abraham Lincoln', 'Thomas Jefferson', 'John Adams'),
('Historia', '¿En qué año declaró Estados Unidos su independencia?', '1776', '1789', '1812', '1492'),
('Historia', '¿Qué líder francés fue derrotado en Waterloo?', 'Napoleón Bonaparte', 'Luis XIV', 'Robespierre', 'Carlos de Gaulle'),
('Historia', '¿Qué inventor europeo es célebre por desarrollar la imprenta de tipos móviles metálicos en el siglo XV?', 'Johannes Gutenberg', 'Galileo Galilei', 'Isaac Newton', 'James Watt'),
('Historia', '¿En qué país comenzó la Revolución Industrial?', 'Gran Bretaña', 'España', 'Rusia', 'Italia'),
('Historia', '¿En qué año ocurrió la Revolución rusa que llevó a los bolcheviques al poder?', '1917', '1905', '1929', '1945'),
('Historia', '¿En qué año llegó el ser humano a la Luna por primera vez?', '1969', '1957', '1961', '1975'),
('Historia', '¿Quién fue el primer ser humano en viajar al espacio?', 'Yuri Gagarin', 'Neil Armstrong', 'Buzz Aldrin', 'John Glenn'),
('Historia', '¿Qué barco se hundió en su viaje inaugural en 1912?', 'Titanic', 'Mayflower', 'Santa María', 'Beagle'),
('Historia', '¿En qué año se creó la Organización de las Naciones Unidas?', '1945', '1919', '1939', '1960'),
('Historia', '¿Qué epidemia devastó gran parte de Europa en el siglo XIV?', 'La peste negra', 'La gripe española', 'La gripe asiática', 'La fiebre amarilla de Buenos Aires'),
('Historia', '¿Qué reina del antiguo Egipto estuvo vinculada a Julio César y Marco Antonio?', 'Cleopatra VII', 'Isabel I', 'Victoria', 'Catalina II'),
-- Ciencia
('Ciencia', '¿Qué planeta está más cerca del Sol?', 'Mercurio', 'Venus', 'Tierra', 'Marte'),
('Ciencia', '¿Cuál es el planeta más grande del sistema solar?', 'Júpiter', 'Saturno', 'Neptuno', 'Tierra'),
('Ciencia', '¿Cuál es el satélite natural de la Tierra?', 'La Luna', 'Fobos', 'Europa', 'Titán'),
('Ciencia', '¿Qué estrella está en el centro del sistema solar?', 'El Sol', 'Sirio', 'Polaris', 'Betelgeuse'),
('Ciencia', '¿Cómo se llama la galaxia en la que está el sistema solar?', 'Vía Láctea', 'Andrómeda', 'Galaxia del Sombrero', 'Triángulo'),
('Ciencia', '¿Qué gas absorben las plantas durante la fotosíntesis?', 'Dióxido de carbono', 'Helio', 'Neón', 'Hidrógeno'),
('Ciencia', '¿Qué pigmento da el color verde a las hojas?', 'Clorofila', 'Melanina', 'Hemoglobina', 'Queratina'),
('Ciencia', '¿Cuál es la unidad básica estructural de los seres vivos?', 'La célula', 'El átomo', 'El órgano', 'El tejido'),
('Ciencia', '¿Qué molécula almacena la información genética en las células humanas?', 'ADN', 'Agua', 'Glucosa', 'Clorofila'),
('Ciencia', '¿Qué orgánulo celular se asocia con la producción de ATP en la respiración aeróbica?', 'La mitocondria', 'El lisosoma', 'El nucléolo', 'La vacuola'),
('Ciencia', '¿Qué órganos filtran la sangre para producir orina?', 'Los riñones', 'Los pulmones', 'Los intestinos', 'Los ovarios'),
('Ciencia', '¿Qué órganos realizan el intercambio de oxígeno y dióxido de carbono al respirar?', 'Los pulmones', 'Los riñones', 'Los huesos', 'Los tendones'),
('Ciencia', '¿Cuál es el hueso más largo del cuerpo humano?', 'El fémur', 'El radio', 'El cúbito', 'La clavícula'),
('Ciencia', '¿Qué células de la sangre transportan la mayor parte del oxígeno?', 'Los glóbulos rojos', 'Las plaquetas', 'Los glóbulos blancos', 'Las neuronas'),
('Ciencia', '¿Qué partícula subatómica tiene carga negativa?', 'El electrón', 'El protón', 'El neutrón', 'El fotón'),
('Ciencia', '¿Qué partícula del núcleo atómico tiene carga positiva?', 'El protón', 'El electrón', 'El neutrón', 'El fotón'),
('Ciencia', '¿Cuál es el símbolo químico del oro?', 'Au', 'Ag', 'Fe', 'Cu'),
('Ciencia', '¿Cuál es el símbolo químico del oxígeno?', 'O', 'Ox', 'Og', 'Au'),
('Ciencia', '¿Cuál es el símbolo químico del hierro?', 'Fe', 'He', 'Ir', 'F'),
('Ciencia', '¿Qué elemento químico tiene el número atómico 1?', 'Hidrógeno', 'Helio', 'Litio', 'Carbono'),
('Ciencia', '¿Cómo se llama el paso de líquido a gas?', 'Vaporización', 'Solidificación', 'Condensación', 'Fusión'),
('Ciencia', '¿Cómo se llama el paso de sólido a líquido?', 'Fusión', 'Sublimación', 'Condensación', 'Deposición'),
('Ciencia', '¿A qué temperatura se congela el agua pura a presión atmosférica normal?', '0 °C', '10 °C', '50 °C', '100 °C'),
('Ciencia', '¿Cuál es la unidad de fuerza en el Sistema Internacional?', 'Newton', 'Julio', 'Pascal', 'Vatio'),
('Ciencia', '¿Cuál es la unidad de energía en el Sistema Internacional?', 'Julio', 'Newton', 'Amperio', 'Metro'),
('Ciencia', '¿Cuál es la unidad de corriente eléctrica en el Sistema Internacional?', 'Amperio', 'Voltio', 'Ohmio', 'Vatio'),
('Ciencia', '¿Qué instrumento mide la temperatura?', 'Termómetro', 'Barómetro', 'Anemómetro', 'Higrómetro'),
('Ciencia', '¿Qué instrumento mide la presión atmosférica?', 'Barómetro', 'Termómetro', 'Cronómetro', 'Sismógrafo'),
('Ciencia', '¿Qué tipo de animal es una ballena?', 'Mamífero', 'Pez', 'Reptil', 'Anfibio'),
('Ciencia', '¿Cuántas patas tiene un insecto adulto típico?', 'Seis', 'Cuatro', 'Ocho', 'Diez'),
-- Arte
('Arte', '¿Quién pintó Guernica?', 'Pablo Picasso', 'Claude Monet', 'Diego Velázquez', 'Vincent van Gogh'),
('Arte', '¿Quién pintó La persistencia de la memoria?', 'Salvador Dalí', 'Pablo Picasso', 'Francisco de Goya', 'Henri Matisse'),
('Arte', '¿Quién pintó Las meninas?', 'Diego Velázquez', 'Leonardo da Vinci', 'Rembrandt', 'Edvard Munch'),
('Arte', '¿Quién pintó El grito?', 'Edvard Munch', 'Claude Monet', 'Salvador Dalí', 'Paul Cézanne'),
('Arte', '¿Quién pintó El nacimiento de Venus del Renacimiento italiano?', 'Sandro Botticelli', 'Caravaggio', 'Rafael', 'Diego Rivera'),
('Arte', '¿Quién esculpió el David de mármol conservado en la Galería de la Academia de Florencia?', 'Miguel Ángel', 'Donatello', 'Auguste Rodin', 'Gian Lorenzo Bernini'),
('Arte', '¿Quién esculpió El pensador?', 'Auguste Rodin', 'Miguel Ángel', 'Antonio Canova', 'Donatello'),
('Arte', '¿Qué artista mexicano pintó numerosos murales y fue pareja de Frida Kahlo?', 'Diego Rivera', 'Fernando Botero', 'Joaquín Torres García', 'Antonio Berni'),
('Arte', '¿Qué pintora mexicana es conocida por sus autorretratos y sus cejas unidas?', 'Frida Kahlo', 'Remedios Varo', 'Leonora Carrington', 'Tarsila do Amaral'),
('Arte', '¿Con qué movimiento artístico se asocia especialmente a Claude Monet?', 'Impresionismo', 'Cubismo', 'Surrealismo', 'Futurismo'),
('Arte', '¿Con qué movimiento artístico se asocia especialmente a Salvador Dalí?', 'Surrealismo', 'Neoclasicismo', 'Realismo', 'Romanticismo'),
('Arte', '¿Qué técnica pictórica utiliza pigmentos diluidos en agua sobre papel?', 'Acuarela', 'Óleo', 'Encáustica', 'Fresco'),
('Arte', '¿Qué técnica consiste en pegar distintos materiales sobre una superficie?', 'Collage', 'Grabado', 'Tallado', 'Modelado'),
('Arte', '¿Qué color resulta de mezclar azul y amarillo en pintura tradicional?', 'Verde', 'Violeta', 'Naranja', 'Rojo'),
('Arte', '¿Qué color resulta de mezclar rojo y amarillo en pintura tradicional?', 'Naranja', 'Verde', 'Azul', 'Violeta'),
('Arte', '¿Quién escribió Hamlet?', 'William Shakespeare', 'Miguel de Cervantes', 'Molière', 'Dante Alighieri'),
('Arte', '¿Quién escribió Cien años de soledad?', 'Gabriel García Márquez', 'Mario Vargas Llosa', 'Julio Cortázar', 'Jorge Luis Borges'),
('Arte', '¿Quién escribió Rayuela?', 'Julio Cortázar', 'Jorge Luis Borges', 'Ernesto Sabato', 'Adolfo Bioy Casares'),
('Arte', '¿Quién escribió El Aleph?', 'Jorge Luis Borges', 'Julio Cortázar', 'Pablo Neruda', 'Roberto Arlt'),
('Arte', '¿Quién escribió La divina comedia?', 'Dante Alighieri', 'Homero', 'Virgilio', 'Giovanni Boccaccio'),
('Arte', '¿A qué poeta de la Antigua Grecia se atribuye la Odisea?', 'Homero', 'Sófocles', 'Eurípides', 'Píndaro'),
('Arte', '¿Quién escribió La metamorfosis, protagonizada por Gregor Samsa?', 'Franz Kafka', 'Hermann Hesse', 'Thomas Mann', 'Albert Camus'),
('Arte', '¿Quién compuso Las cuatro estaciones?', 'Antonio Vivaldi', 'Wolfgang Amadeus Mozart', 'Johann Sebastian Bach', 'Frédéric Chopin'),
('Arte', '¿Quién compuso la Novena sinfonía que incluye la Oda a la alegría?', 'Ludwig van Beethoven', 'Antonio Vivaldi', 'Franz Schubert', 'Piotr Ilich Chaikovski'),
('Arte', '¿Quién compuso La flauta mágica?', 'Wolfgang Amadeus Mozart', 'Giuseppe Verdi', 'Richard Wagner', 'Giacomo Puccini'),
('Arte', '¿Qué instrumento suele tener 88 teclas en su versión moderna estándar?', 'Piano', 'Violín', 'Flauta traversa', 'Trompeta'),
('Arte', '¿Cuántas cuerdas tiene habitualmente un violín?', 'Cuatro', 'Seis', 'Ocho', 'Doce'),
('Arte', '¿Qué instrumento de fuelle es característico del tango rioplatense?', 'Bandoneón', 'Arpa', 'Oboe', 'Trombón'),
('Arte', '¿Qué compositor argentino creó Libertango?', 'Astor Piazzolla', 'Carlos Gardel', 'Atahualpa Yupanqui', 'Alberto Ginastera'),
('Arte', '¿Qué arquitecto diseñó la Casa Batlló de Barcelona?', 'Antoni Gaudí', 'Le Corbusier', 'Frank Lloyd Wright', 'Oscar Niemeyer'),
-- Deportes
('Deportes', '¿Cuántos jugadores por equipo están en cancha en un partido de básquet de cinco contra cinco?', 'Cinco', 'Seis', 'Siete', 'Once'),
('Deportes', '¿Cuántos jugadores por equipo están en cancha en el vóley de sala?', 'Seis', 'Cinco', 'Siete', 'Ocho'),
('Deportes', '¿Cuántos jugadores por equipo participan en un partido de rugby union de quince?', 'Quince', 'Once', 'Trece', 'Siete'),
('Deportes', '¿Cuánto dura el tiempo reglamentario de un partido de fútbol adulto, sin descuentos ni prórroga?', '90 minutos', '60 minutos', '80 minutos', '100 minutos'),
('Deportes', '¿Cuántos puntos vale un triple en básquet?', 'Tres', 'Uno', 'Dos', 'Cuatro'),
('Deportes', '¿Qué superficie caracteriza al torneo de tenis de Wimbledon?', 'Césped', 'Polvo de ladrillo', 'Cemento', 'Arena'),
('Deportes', '¿Qué superficie caracteriza al torneo de tenis de Roland Garros?', 'Polvo de ladrillo', 'Césped', 'Madera', 'Hielo'),
('Deportes', '¿Qué deporte se disputa en el Tour de Francia?', 'Ciclismo', 'Atletismo', 'Motociclismo', 'Automovilismo'),
('Deportes', '¿Cuál es la distancia oficial de una maratón?', '42,195 kilómetros', '21,0975 kilómetros', '40 kilómetros', '50 kilómetros'),
('Deportes', '¿Cuántas pruebas componen un decatlón?', 'Diez', 'Cinco', 'Siete', 'Doce'),
('Deportes', '¿Cuántas pruebas componen un triatlón?', 'Tres', 'Dos', 'Cuatro', 'Cinco'),
('Deportes', '¿Qué disciplinas integran el triatlón estándar?', 'Natación, ciclismo y carrera a pie', 'Remo, salto y carrera a pie', 'Natación, esgrima y tiro', 'Ciclismo, esquí y remo'),
('Deportes', '¿En qué deporte se usa un disco llamado puck?', 'Hockey sobre hielo', 'Béisbol', 'Golf', 'Vóley'),
('Deportes', '¿En qué deporte se utiliza un bate y se recorren bases?', 'Béisbol', 'Rugby', 'Tenis', 'Waterpolo'),
('Deportes', '¿Qué deporte utiliza los términos birdie, par y bogey?', 'Golf', 'Tenis', 'Básquet', 'Boxeo'),
('Deportes', '¿Qué deporte practica un judoca?', 'Judo', 'Karate', 'Taekwondo', 'Esgrima'),
('Deportes', '¿En qué deporte se puede ganar por nocaut?', 'Boxeo', 'Natación', 'Golf', 'Vóley'),
('Deportes', '¿Qué estilo de natación se realiza habitualmente boca arriba?', 'Espalda', 'Mariposa', 'Pecho', 'Crol'),
('Deportes', '¿Qué país ganó el Mundial masculino de fútbol de 1986?', 'Argentina', 'Alemania Federal', 'Brasil', 'Italia'),
('Deportes', '¿Qué país ganó el Mundial masculino de fútbol de 2010?', 'España', 'Países Bajos', 'Alemania', 'Uruguay'),
('Deportes', '¿Qué país ganó el Mundial masculino de fútbol de 2022?', 'Argentina', 'Francia', 'Croacia', 'Marruecos'),
('Deportes', '¿Qué país organizó el primer Mundial masculino de fútbol en 1930?', 'Uruguay', 'Argentina', 'Brasil', 'Italia'),
('Deportes', '¿Qué selección ganó el primer Mundial masculino de fútbol en 1930?', 'Uruguay', 'Argentina', 'Italia', 'Brasil'),
('Deportes', '¿Con qué deporte se asocia a Michael Jordan?', 'Básquet', 'Tenis', 'Fútbol', 'Rugby'),
('Deportes', '¿Con qué deporte se asocia a Roger Federer?', 'Tenis', 'Golf', 'Natación', 'Ciclismo'),
('Deportes', '¿Con qué deporte se asocia a Usain Bolt?', 'Atletismo', 'Natación', 'Boxeo', 'Remo'),
('Deportes', '¿Con qué deporte se asocia a Michael Phelps?', 'Natación', 'Atletismo', 'Ciclismo', 'Tenis'),
('Deportes', '¿Cuántos títulos mundiales de Fórmula 1 ganó Juan Manuel Fangio?', 'Cinco', 'Tres', 'Cuatro', 'Siete'),
('Deportes', '¿Qué ciudad organizó los Juegos Olímpicos de verano de 2016?', 'Río de Janeiro', 'Londres', 'Tokio', 'Pekín'),
('Deportes', '¿Qué ciudad organizó los Juegos Olímpicos de verano de 1992?', 'Barcelona', 'Madrid', 'Atenas', 'Roma'),
-- Cine y TV
('Cine y TV', '¿Quién dirigió Titanic, estrenada en 1997?', 'James Cameron', 'Steven Spielberg', 'Christopher Nolan', 'Ridley Scott'),
('Cine y TV', '¿Quién dirigió la trilogía cinematográfica de El señor de los anillos?', 'Peter Jackson', 'George Lucas', 'Tim Burton', 'David Fincher'),
('Cine y TV', '¿Quién creó la saga cinematográfica Star Wars?', 'George Lucas', 'James Cameron', 'Stanley Kubrick', 'Alfred Hitchcock'),
('Cine y TV', '¿Qué actor interpreta a Jack en Titanic de 1997?', 'Leonardo DiCaprio', 'Brad Pitt', 'Tom Cruise', 'Matt Damon'),
('Cine y TV', '¿Qué actriz interpreta a Rose en Titanic de 1997?', 'Kate Winslet', 'Nicole Kidman', 'Julia Roberts', 'Sandra Bullock'),
('Cine y TV', '¿Qué actor interpreta a Neo en la película Matrix de 1999?', 'Keanu Reeves', 'Will Smith', 'Tom Hanks', 'Johnny Depp'),
('Cine y TV', '¿Cómo se llama el protagonista de la saga de películas de Harry Potter?', 'Harry Potter', 'Draco Malfoy', 'Cedric Diggory', 'Neville Longbottom'),
('Cine y TV', '¿Cómo se llama la escuela de magia a la que asiste Harry Potter?', 'Hogwarts', 'Narnia', 'Gondor', 'Nevermore'),
('Cine y TV', '¿Qué actor interpreta a Harry Potter en las ocho películas principales?', 'Daniel Radcliffe', 'Rupert Grint', 'Tom Felton', 'Elijah Wood'),
('Cine y TV', '¿Cómo se llama el hobbit encargado de llevar el Anillo Único a Mordor?', 'Frodo Bolsón', 'Bilbo Bolsón', 'Merry Brandigamo', 'Pippin Tuk'),
('Cine y TV', '¿Qué personaje de El señor de los anillos es un mago?', 'Gandalf', 'Legolas', 'Gimli', 'Aragorn'),
('Cine y TV', '¿Qué estudio produjo la película Toy Story de 1995?', 'Pixar', 'DreamWorks', 'Studio Ghibli', 'Aardman'),
('Cine y TV', '¿Cómo se llama el pez payaso que Marlin busca en una película de Pixar?', 'Nemo', 'Dory', 'Bruce', 'Crush'),
('Cine y TV', '¿Qué clase de animal es Shrek?', 'Un ogro', 'Un dragón', 'Un duende', 'Un cíclope'),
('Cine y TV', '¿Cómo se llama el león protagonista de El rey león?', 'Simba', 'Mufasa', 'Scar', 'Rafiki'),
('Cine y TV', '¿Cómo se llama la hermana de Elsa en Frozen?', 'Anna', 'Ariel', 'Bella', 'Jasmín'),
('Cine y TV', '¿Qué personaje de Disney pierde una zapatilla de cristal?', 'Cenicienta', 'Blancanieves', 'Aurora', 'Rapunzel'),
('Cine y TV', '¿Qué personaje de Disney es un muñeco de madera cuya nariz crece al mentir?', 'Pinocho', 'Dumbo', 'Bambi', 'Peter Pan'),
('Cine y TV', '¿Cómo se llama el astronauta de juguete de Toy Story?', 'Buzz Lightyear', 'Woody', 'Slinky', 'Rex'),
('Cine y TV', '¿Qué superhéroe tiene como identidad civil a Bruce Wayne?', 'Batman', 'Superman', 'Spider-Man', 'Iron Man'),
('Cine y TV', '¿Qué superhéroe tiene como identidad civil a Peter Parker?', 'Spider-Man', 'Batman', 'Thor', 'Hulk'),
('Cine y TV', '¿Qué superhéroe de Marvel utiliza un martillo llamado Mjolnir?', 'Thor', 'Iron Man', 'Hawkeye', 'Ant-Man'),
('Cine y TV', '¿Qué actor interpreta a Iron Man en la película de 2008?', 'Robert Downey Jr.', 'Chris Evans', 'Chris Hemsworth', 'Mark Ruffalo'),
('Cine y TV', '¿Cómo se llama el padre de la familia Simpson?', 'Homero', 'Bart', 'Ned', 'Moe'),
('Cine y TV', '¿Cómo se llama la madre de la familia Simpson?', 'Marge', 'Lisa', 'Maggie', 'Patty'),
('Cine y TV', '¿Qué serie tiene como protagonistas a Ross, Rachel, Monica, Chandler, Joey y Phoebe?', 'Friends', 'Seinfeld', 'The Office', 'Modern Family'),
('Cine y TV', '¿Qué serie sigue al profesor Walter White y a Jesse Pinkman?', 'Breaking Bad', 'Lost', 'House', 'Dexter'),
('Cine y TV', '¿Qué serie española incluye personajes llamados El Profesor y Tokio?', 'La casa de papel', 'Élite', 'Merlí', 'Los simuladores'),
('Cine y TV', '¿Qué director japonés dirigió El viaje de Chihiro?', 'Hayao Miyazaki', 'Akira Kurosawa', 'Isao Takahata', 'Satoshi Kon'),
('Cine y TV', '¿Qué película argentina de 2009 dirigida por Juan José Campanella ganó un Óscar?', 'El secreto de sus ojos', 'Relatos salvajes', 'Nueve reinas', 'El hijo de la novia');

START TRANSACTION;

INSERT INTO category (name)
SELECT DISTINCT t.categoria FROM carga_preguntas_extra t
WHERE NOT EXISTS (SELECT 1 FROM category c WHERE c.name = t.categoria);

-- Registrar cuáles ya existían para no cambiar sus opciones.
UPDATE carga_preguntas_extra t
JOIN category c ON c.name = t.categoria
JOIN questions q ON q.category_id = c.id AND q.question = t.pregunta
SET t.existente_id = q.id;

INSERT INTO questions (category_id, question, difficulty_level, points)
SELECT c.id, t.pregunta, 1, 10
FROM carga_preguntas_extra t JOIN category c ON c.name = t.categoria
WHERE t.existente_id IS NULL;

INSERT INTO response (question_id, response, is_correct)
SELECT q.id, t.correcta, 1
FROM carga_preguntas_extra t JOIN category c ON c.name = t.categoria
JOIN questions q ON q.category_id = c.id AND q.question = t.pregunta
WHERE t.existente_id IS NULL;

INSERT INTO response (question_id, response, is_correct)
SELECT q.id, t.incorrecta1, 0
FROM carga_preguntas_extra t JOIN category c ON c.name = t.categoria
JOIN questions q ON q.category_id = c.id AND q.question = t.pregunta
WHERE t.existente_id IS NULL;

INSERT INTO response (question_id, response, is_correct)
SELECT q.id, t.incorrecta2, 0
FROM carga_preguntas_extra t JOIN category c ON c.name = t.categoria
JOIN questions q ON q.category_id = c.id AND q.question = t.pregunta
WHERE t.existente_id IS NULL;

INSERT INTO response (question_id, response, is_correct)
SELECT q.id, t.incorrecta3, 0
FROM carga_preguntas_extra t JOIN category c ON c.name = t.categoria
JOIN questions q ON q.category_id = c.id AND q.question = t.pregunta
WHERE t.existente_id IS NULL;

COMMIT;
DROP TEMPORARY TABLE carga_preguntas_extra;
