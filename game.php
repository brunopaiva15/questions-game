<?php

// Récupérer scale dans l'URL
$scale = $_GET['scale'] ?? 75;

// Récupérer le timer dans l'URL
$timer = $_GET['timer'] ?? 35;

// Scales possibles : 50, 75, 90, 95, 100, 105, 110, 125, 150. Si la valeur récupérée n'est pas dans la liste, on stoppe le script et on affiche un message d'erreur
if (!in_array($scale, ['50', '75', '90', '95', '100', '105', '110', '125', '150'])) {
    die('La valeur de l\'échelle doit être 50, 75, 90, 95, 100, 105, 110, 125 ou 150.');
}

?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>La minute culture</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .border-timer {
            top: 0;
            left: 0;
            bottom: 0;
            right: 0;
            pointer-events: none;
            z-index: 10;
        }

        .border {
            stroke-dasharray: 0;
            stroke-dashoffset: 0;
        }

        .animate-slideIn {
            animation: slideIn 1s ease-out forwards;
        }

        @keyframes slideIn {
            from {
                transform: translateY(-10px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
    </style>
</head>

<body class="flex justify-center items-center h-screen bg-gray-100">
    <div class="scale-container transform scale-<?php echo htmlspecialchars($scale); ?>">
        <div class="container bg-white p-16 rounded-lg shadow-lg text-center max-w-5xl w-full transform -translate-y-5 animate-slideIn relative">
            <div class="border-timer absolute inset-0 pointer-events-none">
                <svg width="100%" height="100%">
                    <path class="border" d="M 13,3.5 H 1147 A 10,10 0 0 1 1147,13.5 V 887.5 A 10,10 0 0 1 1147,897.5 H 13 A 10,10 0 0 1 3,887.5 V 13.5 A 10,10 0 0 1 13,3.5 Z" fill="none" stroke="#2ecc71" stroke-width="5.5" />
                </svg>
            </div>
            <?php

            date_default_timezone_set('Europe/Zurich');
            require 'vendor/autoload.php';

            use GuzzleHttp\Client;

            $jsonFilePath = 'question_of_the_day.json';
            $usedQuestionsFilePath = 'used_questions.txt';

            $currentDate = date('Y-m-d');
            $currentHour = date('H');

            $segment = '';
            if ($currentHour >= 6 && $currentHour < 12) {
                $segment = 'matin';
            } elseif ($currentHour >= 12 && $currentHour < 18) {
                $segment = 'apres_midi';
            } elseif ($currentHour >= 18 && $currentHour < 24) {
                $segment = 'soir';
            } else {
                $segment = 'nuit';
            }

            $question = '';
            $answers = [];
            $correctAnswer = '';
            $explanation = '';
            $theme = '';

            if (file_exists($jsonFilePath)) {
                $jsonContent = file_get_contents($jsonFilePath);
                $questionsData = json_decode($jsonContent, true);

                if (isset($questionsData[$currentDate][$segment])) {
                    $question = $questionsData[$currentDate][$segment]['question'] ?? '';
                    $answers = $questionsData[$currentDate][$segment]['answers'] ?? [];
                    $correctAnswer = $questionsData[$currentDate][$segment]['correct_answer'] ?? '';
                    $explanation = $questionsData[$currentDate][$segment]['explanation'] ?? '';
                    $theme = $questionsData[$currentDate][$segment]['theme'] ?? '';
                }
            }

            if (empty($question) || empty($answers) || empty($correctAnswer) || empty($explanation) || empty($theme)) {
                $themes = ['Géographie', 'Divertissement', 'Histoire', 'Art et Littérature', 'Science et Nature', 'Sports et Loisirs', 'Technologie', 'Cuisine et Gastronomie'];
                $theme = $themes[array_rand($themes)];

                $usedQuestions = file_exists($usedQuestionsFilePath) ? file($usedQuestionsFilePath, FILE_IGNORE_NEW_LINES) : [];

                do {
                    $client = new Client(['verify' => false]);
                    $response = $client->post(
                        'https://api.openai.com/v1/chat/completions',
                        [
                            'headers' => [
                                'Content-Type' => 'application/json',
                                'Authorization' => 'Bearer API_KEY',
                            ],
                            'json' => [
                                'model' => 'gpt-4o',
                                'messages' => [
                                    ['role' => 'system', 'content' => 'Génère une question de culture générale en français sur le thème suivant : ' . $theme . '. La question doit être originale avec quatre réponses courtes possibles, en format JSON. Une seule réponse doit être correcte. Fais en sorte de mélanger la bonne réponse parmi les fausses. Il doit toujours y avoir un espace entre la question et le point d\'interrogation. Ajoute également courte (environ 2 phrases) une explication culturelle liée à la réponse correcte. Le format doit être: {"question": "question", "correct_answer": "correct_answer", "answers": ["answer1", "answer2", "answer3", "answer4"], "explanation": "explication"}'],
                                    ['role' => 'user', 'content' => ''],
                                ],
                            ],
                        ]
                    );

                    $responseBody = json_decode($response->getBody()->getContents(), true);
                    $content = json_decode($responseBody['choices'][0]['message']['content'], true);

                    $question = $content['question'] ?? '';
                    $answers = $content['answers'] ?? [];
                    $correctAnswer = $content['correct_answer'] ?? '';
                    $explanation = $content['explanation'] ?? '';

                    // Vérifie si la question a déjà été utilisée
                    $alreadyUsed = in_array($question, $usedQuestions);
                } while ($alreadyUsed || empty($question) || empty($answers) || empty($correctAnswer) || empty($explanation));

                $questionsData[$currentDate][$segment] = ['question' => $question, 'answers' => $answers, 'correct_answer' => $correctAnswer, 'explanation' => $explanation, 'theme' => $theme];
                file_put_contents($jsonFilePath, json_encode($questionsData));

                // Ajouter la question à la liste des questions déjà utilisées et enregistrer dans used_questions.txt
                $usedQuestions[] = $question;
                file_put_contents($usedQuestionsFilePath, implode("\n", $usedQuestions));
            }

            $themeColors = [
                'Géographie' => '#1E90FF',     // Bleu
                'Divertissement' => '#FF69B4', // Rose
                'Histoire' => '#D4B40B',       // Jaune
                'Art et Littérature' => '#8A2BE2', // Violet
                'Science et Nature' => '#32CD32',  // Vert
                'Sports et Loisirs' => '#FF4500',   // Orange
                'Technologie' => '#3AB7E0',        // Bleu clair
                'Cuisine et Gastronomie' => '#FF8C00', // Orange foncé
            ];

            $themeColor = $themeColors[$theme] ?? '#333';

            ?>
            <div class="theme inline-block px-4 py-2 text-2xl mb-4 rounded bg-[<?php echo htmlspecialchars($themeColor); ?>] text-white"><?php echo htmlspecialchars($theme); ?></div>
            <div class="question font-bold mb-12" style="font-size: 3.25rem; line-height: 1;"><?php echo htmlspecialchars($question); ?></div>
            <div class="answers flex flex-col items-center" data-correct-answer="<?php echo htmlspecialchars($correctAnswer); ?>">
                <?php
                foreach ($answers as $answer) {
                    echo "<div class='answer relative bg-gray-200 p-5 rounded-lg mb-2 w-4/5 text-left text-4xl transition-all duration-300 ease-in-out'>" . htmlspecialchars($answer) . "</div>";
                }
                ?>
            </div>
            <div class="explanation text-xl mt-8 text-gray-700 hidden opacity-0 transition-opacity duration-1000"><?php echo htmlspecialchars($explanation); ?></div>
        </div>
    </div>
    <script>
        // Définir la couleur de la bordure comme la couleur du thème
        document.querySelector('.border').style.stroke = '<?php echo $themeColor; ?>';

        function revealAnswer() {
            const correctAnswerText = document.querySelector('.answers').dataset.correctAnswer;
            const answers = document.querySelectorAll('.answer');
            answers.forEach(answer => {
                if (answer.textContent.trim() === correctAnswerText) {
                    answer.classList.add('bg-green-500', 'text-white', 'transform', 'scale-105');
                }
            });

            // Afficher l'explication de manière fluide
            const explanation = document.querySelector('.explanation');
            explanation.classList.remove('hidden');
            explanation.classList.add('opacity-100');

            console.log('Bonne réponse:', correctAnswerText);
        }

        setTimeout(revealAnswer, <?php echo $timer * 1000; ?>);

        // Déclencher l'animation de la bordure
        document.addEventListener('DOMContentLoaded', () => {
            // Récupérer le conteneur et vérifier qu'il existe
            const container = document.querySelector('.container');
            if (!container) return;

            // Récupérer la hauteur du conteneur
            const containerHeight = container.offsetHeight;

            // Récupérer la largeur du conteneur
            const containerWidth = container.offsetWidth;

            // Sélectionner le path du svg et vérifier qu'il existe
            const path = document.querySelector('.border');
            if (!path) return;

            // Calculer la nouvelle valeur pour le path en fonction de la hauteur du conteneur
            const newPath = `M 13,2.5 H ${containerWidth - 13} A 10,10 0 0 1 ${containerWidth - 2.5},${12.5} V ${containerHeight - 12.5} A 10,10 0 0 1 ${containerWidth - 13},${containerHeight - 2.5} H 13 A 10,10 0 0 1 3,${containerHeight - 12.5} V ${12.5} A 10,10 0 0 1 13,2.5 Z`;

            // Appliquer la nouvelle valeur au path
            path.setAttribute('d', newPath);

            // Calculer la longueur totale du chemin et définir les propriétés d'animation
            const length = path.getTotalLength();
            path.style.strokeDasharray = length;
            path.style.strokeDashoffset = '0'; // Initialement, l'offset est à 0 (bordure complète)

            // Forcer le recalcul du style pour démarrer l'animation
            path.getBoundingClientRect();

            // Appliquer la transition
            path.style.transition = 'stroke-dashoffset <?php echo $timer; ?>s linear';
            path.style.strokeDashoffset = length; // L'offset passe à la longueur totale (désagrégation)

        });
    </script>
</body>

</html>
