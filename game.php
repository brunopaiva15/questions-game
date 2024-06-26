<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>La minute culture</title>
    <style>
        @font-face {
            font-family: "Inter";
            src: url("Inter/static/Inter-Regular.ttf") format("truetype");
            font-weight: 400;
            font-style: normal;
        }

        @font-face {
            font-family: "Inter";
            src: url("Inter/static/Inter-Bold.ttf") format("truetype");
            font-weight: 700;
            font-style: normal;
        }

        @font-face {
            font-family: "Inter";
            src: url("Inter/static/Inter-Black.ttf") format("truetype");
            font-weight: 900;
            font-style: normal;
        }

        body {
            font-family: "Inter", sans-serif;
            background: #eee;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }

        .container {
            background: #fff;
            padding: 90px;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            text-align: center;
            max-width: 1000px;
            width: 100%;
            opacity: 0;
            transform: translateY(-20px);
            animation: slideIn 0.5s forwards;
        }

        .title {
            font-size: 70px;
            margin-top: 0px;
        }

        .question {
            font-size: 57px;
            margin-bottom: 50px;
            font-weight: 700;
        }

        .answers {
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .answer {
            background: #f2f2f2;
            padding: 20px;
            border-radius: 10px;
            margin: 10px;
            width: 80%;
            text-align: left;
            font-size: 47px;
            transition: background 0.3s ease;
        }

        .correct {
            background: #61ba64 !important;
        }

        @keyframes slideIn {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <?php
        date_default_timezone_set('Europe/Zurich');
        require 'vendor/autoload.php';

        use GuzzleHttp\Client;

        $jsonFilePath = 'question_of_the_day.json';
        $usedQuestionsFilePath = 'used_questions.txt';

        $currentDate = date('Y-m-d');
        $currentTime = date('A');

        $question = '';
        $answers = [];
        $correctAnswer = '';

        if (file_exists($jsonFilePath)) {
            $jsonContent = file_get_contents($jsonFilePath);
            $questionsData = json_decode($jsonContent, true);

            if (isset($questionsData[$currentDate][$currentTime])) {
                $question = $questionsData[$currentDate][$currentTime]['question'] ?? '';
                $answers = $questionsData[$currentDate][$currentTime]['answers'] ?? [];
                $correctAnswer = $questionsData[$currentDate][$currentTime]['correct_answer'] ?? '';
            }
        }

        if (empty($question) || empty($answers) || empty($correctAnswer)) {
            $usedQuestions = file_exists($usedQuestionsFilePath) ? file($usedQuestionsFilePath, FILE_IGNORE_NEW_LINES) : [];

            do {
                $client = new Client(['verify' => false]);
                $response = $client->post(
                    'https://api.openai.com/v1/chat/completions',
                    [
                        'headers' => [
                            'Content-Type' => 'application/json',
                            'Authorization' => 'Bearer OPENAI-API-KEY',
                        ],
                        'json' => [
                            'model' => 'gpt-4o',
                            'messages' => [
                                ['role' => 'system', 'content' => 'Génère une question tout public de culture générale totalement aléatoire et relativement originale en français avec quatre réponses possibles, en format JSON. Une seule réponse doit être correcte. Fais en sorte de mélanger la bonne réponse parmis les fausses. Il doit toujours y avoir un espace entre la question et le point d\'interrogation. Le format doit être: {"question": "question", "correct_answer": "correct_answer", "answers": ["answer1", "answer2", "answer3", "answer4"]}'],
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

                // Vérifie si la question a déjà été utilisée
                $alreadyUsed = in_array($question, $usedQuestions);
            } while ($alreadyUsed || empty($question) || empty($answers) || empty($correctAnswer));

            $questionsData[$currentDate][$currentTime] = ['question' => $question, 'answers' => $answers, 'correct_answer' => $correctAnswer];
            file_put_contents($jsonFilePath, json_encode($questionsData));

            // Ajouter la question à la liste des questions déjà utilisées et enregistrer dans used_questions.txt
            $usedQuestions[] = $question;
            file_put_contents($usedQuestionsFilePath, implode("\n", $usedQuestions));
        }
        ?>
        <div class="question"><?php echo htmlspecialchars($question); ?></div>
        <div class="answers" data-correct-answer="<?php echo htmlspecialchars($correctAnswer); ?>">
            <?php
            foreach ($answers as $answer) {
                echo "<div class='answer'>" . htmlspecialchars($answer) . "</div>";
            }
            ?>
        </div>
    </div>
    <script>
        function revealAnswer() {
            const correctAnswerText = document.querySelector('.answers').dataset.correctAnswer;
            const answers = document.querySelectorAll('.answer');
            answers.forEach(answer => {
                if (answer.textContent.trim() === correctAnswerText) {
                    answer.style.backgroundColor = '#4CAF50';
                }
            });
            console.log('Bonne réponse:', correctAnswerText);
        }

        setTimeout(revealAnswer, 40000);
    </script>
</body>

</html>
