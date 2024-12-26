<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$category = isset($_GET['category']) ? $_GET['category'] : 'editing';

try {
    $stmt = $conn->prepare("SELECT * FROM Questions WHERE category = ? ORDER BY RAND() LIMIT 15");
    $stmt->bind_param("s", $category);
    $stmt->execute();
    $result = $stmt->get_result();
    $questions = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch(Exception $e) {
    die("Error fetching questions: " . $e->getMessage());
}

if (!isset($_SESSION['lives'])) {
    $_SESSION['lives'] = 3;
}
if (!isset($_SESSION['pretest_start'])) {
    $_SESSION['pretest_start'] = time();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pre-test</title>
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .question-modal {
            display: none;
        }
        .question-modal.active {
            display: block;
        }
        .correct-answer {
            background-color: #d4edda !important;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 10px;
        }
        .wrong-answer {
            background-color: #f8d7da !important;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 10px;
        }
        .form-check {
            padding: 15px;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .form-check:hover {
            background-color: #f8f9fa;
        }
        .form-check-input {
            margin-top: 3px;
        }
        .form-check-label {
            width: 100%;
            cursor: pointer;
            margin-left: 10px;
        }
        .card {
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .btn-navigation {
            width: 100%;
            margin-bottom: 10px;
        }
        .progress-info {
            margin-bottom: 20px;
        }
        .score-summary {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
<div class="container">
    <h1 class="mt-5">Pre-test</h1>
    <div class="alert alert-info progress-info">
        <p>Lives: <span id="lives"><?php echo $_SESSION['lives']; ?></span></p>
        <p>Time remaining: <span id="time">60</span> seconds</p>
    </div>

    <div id="quiz-container">
        <?php foreach ($questions as $index => $question): ?>
            <div id="question-<?php echo $index; ?>" class="question-modal <?php echo $index === 0 ? 'active' : ''; ?>">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Question <?php echo $index + 1; ?> of <?php echo count($questions); ?></h5>
                        <p class="card-text"><?php echo $question['question_text']; ?></p>

                        <div class="form-check">
                            <input class="form-check-input" type="radio"
                                   name="question<?php echo $index; ?>"
                                   id="choice_a_<?php echo $index; ?>"
                                   value="A">
                            <label class="form-check-label" for="choice_a_<?php echo $index; ?>">
                                A. <?php echo $question['choice_a']; ?>
                            </label>
                        </div>

                        <div class="form-check">
                            <input class="form-check-input" type="radio"
                                   name="question<?php echo $index; ?>"
                                   id="choice_b_<?php echo $index; ?>"
                                   value="B">
                            <label class="form-check-label" for="choice_b_<?php echo $index; ?>">
                                B. <?php echo $question['choice_b']; ?>
                            </label>
                        </div>

                        <div class="form-check">
                            <input class="form-check-input" type="radio"
                                   name="question<?php echo $index; ?>"
                                   id="choice_c_<?php echo $index; ?>"
                                   value="C">
                            <label class="form-check-label" for="choice_c_<?php echo $index; ?>">
                                C. <?php echo $question['choice_c']; ?>
                            </label>
                        </div>

                        <div class="form-check">
                            <input class="form-check-input" type="radio"
                                   name="question<?php echo $index; ?>"
                                   id="choice_d_<?php echo $index; ?>"
                                   value="D">
                            <label class="form-check-label" for="choice_d_<?php echo $index; ?>">
                                D. <?php echo $question['choice_d']; ?>
                            </label>
                        </div>

                        <div class="mt-3">
                            <button type="button" class="btn btn-primary next-btn"
                                    data-question="<?php echo $question['question_id']; ?>"
                                    data-index="<?php echo $index; ?>">
                                <?php echo $index < count($questions) - 1 ? 'Next' : 'Finish Quiz'; ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Game Over Modal -->
<div class="modal fade" id="gameOverModal" tabindex="-1" role="dialog" aria-hidden="true" data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Quiz Complete!</h5>
            </div>
            <div class="modal-body">
                <div class="text-center mb-4">
                    <h3>Your Score: <span id="finalScore">0</span></h3>
                    <p>Lives Remaining: <span id="finalLives">0</span></p>
                    <p>Time Taken: <span id="timeTaken">0</span> seconds</p>
                </div>
                <div class="text-center">
                    <button type="button" class="btn btn-primary btn-navigation" onclick="showResults()">View Results</button>
                    <a href="leaderboard.php" class="btn btn-info btn-navigation">Leaderboards</a>
                    <a href="index.php" class="btn btn-secondary btn-navigation">Back to Home</a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Results Modal -->
<div class="modal fade" id="resultsModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detailed Results</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
                <div class="score-summary mb-4">
                    <h4>Score Summary</h4>
                    <p>Total Score: <span id="resultsTotalScore">0</span></p>
                    <p>Time Taken: <span id="resultsTotalTime">0</span> seconds</p>
                    <p>Lives Remaining: <span id="resultsLivesLeft">0</span></p>
                </div>
                <div id="detailedResults">
                    <!-- Results will be populated here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Back to Summary</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>


<script>
$(document).ready(function() {
    let currentQuestion = 0;
    let timer;
    let lives = <?php echo $_SESSION['lives']; ?>;
    let quizResults = [];
    let startTime = new Date();

    function resetTimer() {
        clearInterval(timer);
        let timeRemaining = 60;
        $('#time').text(timeRemaining);

        timer = setInterval(() => {
            timeRemaining--;
            $('#time').text(timeRemaining);

            if (timeRemaining <= 0) {
                clearInterval(timer);
                handleTimeUp();
            }
        }, 1000);
    }

    function handleTimeUp() {
        lives--;
        $('#lives').text(lives);

        if (lives <= 0) {
            showGameOver();
            return;
        }
        moveToNextQuestion();
    }

    function moveToNextQuestion() {
        $('.question-modal').removeClass('active');
        currentQuestion++;

        if (currentQuestion < <?php echo count($questions); ?>) {
            $(`#question-${currentQuestion}`).addClass('active');
            resetTimer();
        } else {
            showGameOver();
        }
    }

    function submitResults(callback) {
        const correctAnswers = quizResults.filter(result => result.is_correct).length;
        const timeTaken = Math.floor((new Date() - startTime) / 1000);
        const remainingQuestions = <?php echo count($questions); ?> - currentQuestion;
        const totalTime = timeTaken + (remainingQuestions * 60);

        $.ajax({
            type: 'POST',
            url: 'submit_results.php',
            data: {
                score: correctAnswers,
                total_questions: quizResults.length,
                time_taken: totalTime,
                lives_remaining: lives,
                category: '<?php echo $category; ?>'
            },
            success: function(response) {
                if (callback) callback();
            },
            error: function() {
                console.error('Error submitting results');
                if (callback) callback();
            }
        });
    }

    function showGameOver() {
        clearInterval(timer);
        const timeTaken = Math.floor((new Date() - startTime) / 1000);
        const remainingQuestions = <?php echo count($questions); ?> - currentQuestion;
        const totalTime = timeTaken + (remainingQuestions * 60);
        const correctAnswers = quizResults.filter(result => result.is_correct).length;

        $('#finalScore').text(correctAnswers + ' / ' + quizResults.length);
        $('#finalLives').text(lives);
        $('#timeTaken').text(totalTime);

        submitResults(function() {
            $('#gameOverModal').modal({
                backdrop: 'static',
                keyboard: false
            });
        });
    }

    function showResults() {
        $('#resultsTotalScore').text($('#finalScore').text());
        $('#resultsTotalTime').text($('#timeTaken').text());
        $('#resultsLivesLeft').text($('#finalLives').text());

        let resultsHtml = '';
        quizResults.forEach((result, index) => {
            resultsHtml += `
                <div class="${result.is_correct ? 'correct-answer' : 'wrong-answer'}">
                    <h5>Question ${index + 1}</h5>
                    <p><strong>Question:</strong> ${result.question_text}</p>
                    <p><strong>Your Answer:</strong> ${result.user_answer_text}</p>
                    ${!result.is_correct ?
                        `<p><strong>Correct Answer:</strong> ${result.correct_answer_text}</p>`
                        : ''}
                </div>
            `;
        });

        $('#detailedResults').html(resultsHtml);
        $('#gameOverModal').modal('hide');
        $('#resultsModal').modal('show');
    }

    // Initialize timer
    resetTimer();

    // Make form-check clickable
    $('.form-check').click(function() {
        $(this).find('input[type="radio"]').prop('checked', true);
    });

    // Handle results modal close
    $('#resultsModal').on('hidden.bs.modal', function () {
        $('#gameOverModal').modal('show');
    });

    // Make View Results button clickable
    $(document).on('click', '[onclick="showResults()"]', function() {
        showResults();
    });

    // Handle Next button clicks
    $('.next-btn').click(function() {
        console.log('Next button clicked'); // Debug log
        const questionId = $(this).data('question');
        const index = $(this).data('index');
        const selectedAnswer = $(`input[name="question${index}"]:checked`).val();

        if (!selectedAnswer) {
            alert('Please select an answer before proceeding.');
            return;
        }

        // Show loading state
        $(this).prop('disabled', true).text('Checking...');
        const $btn = $(this);

        $.ajax({
            type: 'POST',
            url: 'check_answer.php',
            data: {
                question_id: questionId,
                user_answer: selectedAnswer
            },
            success: function(response) {
                try {
                    console.log('Response:', response); // Debug log
                    const result = JSON.parse(response);
                    const currentQuestion = $(`#question-${index}`);

                    quizResults.push({
                        question_text: currentQuestion.find('.card-text').text(),
                        user_answer: selectedAnswer,
                        user_answer_text: currentQuestion.find(`label[for="choice_${selectedAnswer.toLowerCase()}_${index}"]`).text().trim(),
                        correct_answer: result.correct_answer,
                        correct_answer_text: currentQuestion.find(`label[for="choice_${result.correct_answer.toLowerCase()}_${index}"]`).text().trim(),
                        is_correct: result.is_correct
                    });

                    if (!result.is_correct) {
                        lives--;
                        $('#lives').text(lives);

                        if (lives <= 0) {
                            showGameOver();
                            return;
                        }
                    }
                    moveToNextQuestion();
                } catch (e) {
                    console.error('Error parsing response:', e, response);
                    alert('Error checking answer. Please try again.');
                }
            },
            error: function(xhr, status, error) {
                console.error('Ajax error:', error);
                alert('Error checking answer. Please try again.');
            },
            complete: function() {
                // Reset button state
                $btn.prop('disabled', false).text('Next');
            }
        });
    });
});

// Make showResults available globally
window.showResults = function() {
    $('#resultsTotalScore').text($('#finalScore').text());
    $('#resultsTotalTime').text($('#timeTaken').text());
    $('#resultsLivesLeft').text($('#finalLives').text());

    let resultsHtml = '';
    quizResults.forEach((result, index) => {
        resultsHtml += `
            <div class="${result.is_correct ? 'correct-answer' : 'wrong-answer'}">
                <h5>Question ${index + 1}</h5>
                <p><strong>Question:</strong> ${result.question_text}</p>
                <p><strong>Your Answer:</strong> ${result.user_answer_text}</p>
                ${!result.is_correct ?
                    `<p><strong>Correct Answer:</strong> ${result.correct_answer_text}</p>`
                    : ''}
            </div>
        `;
    });

    $('#detailedResults').html(resultsHtml);
    $('#gameOverModal').modal('hide');
    $('#resultsModal').modal('show');
};

</script>
</body>
</html>