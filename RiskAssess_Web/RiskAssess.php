<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header("Location: login.php");
    exit;
}
require_once 'config.php';
// Map question IDs to exact feature names from your CSV/model
// Ensure all question IDs map to exactly what the model expects
$questionIdToFeatureName = [
    'age' => 'What is your age?',
    'gender' => 'What is your gender?',
    'education' => 'What is your highest level of education?',
    'employment' => 'What is your current employment status?',
    'marital' => 'What is your marital status?',
    'family_income' => 'What is your family’s income level?',
    'personal_income' => 'What is your personal monthly income range?',
    'residence' => 'Where do you currently live?',
    'province' => 'Which province of Sri Lanka do you currently reside in?',
    'drug_prevalent' => 'Do you live in an area where drug use is prevalent?',
    'drug_accessibility' => 'How accessible are drugs in your area?',
    'high_crime' => 'Do you live in a high-crime neighborhood?',
    'time_with' => 'Who do you spend most of your time with?',
    'daily_activity' => 'How do you spend most of your time daily? (Choose one that best describes your day)',
    'family_relationship' => 'How would you describe your relationship with your family?',
    'family_substance' => 'Do you have a family history of substance abuse?',
    'friends_drugs' => 'Do you have friends who use drugs?',
    'family_addiction' => 'Do You Have an Addicted Person in Your Family?',
    'trauma' => 'Have you experienced any traumatic events in your life?',
    'taken_drugs' => 'Ever taken drugs?',
    'first_use_age' => 'At what age did you first use drugs?',
    'substances' => 'Have you ever used any of the following substances? (Please select all that apply. If the drug you\'ve used isn\'t listed, feel free to mention it.)',
    'frequency' => 'Which of the Following Best Describes Your Drug Use Frequency?',
    'enhance_experience' => 'How many drugs are typically required to enhance the experience or achieve a feeling of being high?',
    'reason' => 'What was the main reason you tried drugs? ( Please select the main reason )',
    'external_factors' => 'Do You Believe External Factors (e.g., Peer or Family Pressure) Make You More Likely to Use Drugs?',
    'relationship_affected' => 'Has drug use negatively affected your relationships (family, friends, colleagues)?',
    'quit_plan' => 'Do You Have Plans or Desire to Quit drugs (if you do use)?',
    'cravings' => 'Do you experience cravings for drugs?',
    'withdrawal' => 'Have you ever experienced withdrawal symptoms from drug use?',
    'control' => 'Is It Easy to Control Your Drug Usage?',
    'mental_problems' => 'Do You Experience Any of These Mental/Emotional Problems?',
    'tobacco' => 'Do You Smoke Tobacco or Similar Products?',
    'close_friends' => 'Number of Close Friends',
    'friends_influence' => 'Do Your Friends Influence Your Drug Use?',
    'try_new' => 'If Given the Chance, Would You Try or Taste a New Drug?',
    'nights_friends' => 'Do You Often Spend Nights at Friends Houses?',
    'law_conflict' => 'Do You Ever Experience Conflict With the Law?',
    'court_case' => 'Do You Have Any Case in Court?',
    'living_with_user' => 'Are You Currently Living With a Drug User?',
    'failure' => 'Have You Ever Felt That You Failed in a Significant Aspect of Life?',
    'suicidal' => 'Have You Ever Had Suicidal Thoughts?',
    'satisfied' => 'Are you satisfied with your workplace/studies?',
    'motivation' => 'How motivated are you to stay sober or reduce your substance use? (1-10 scale)'
];


// Define question categories and group questions
$questionCategories = [
    [
        "id" => "demographics", "title" => "Demographics & Background", "icon" => "person", "description" => "Basic information about yourself",
        "questions" => [
            ["id" => "age", "text" => "What is your age?", "type" => "select", "options" => ["15-18", "18-35", "26-35", "36-45", "46+"]],
            ["id" => "gender", "text" => "What is your gender?", "type" => "select", "options" => ["Male", "Female", "Prefer not to say"]],
            ["id" => "education", "text" => "What is your highest level of education?", "type" => "select", "options" => ["No formal education", "O Levels", "A/Ls", "Undergraduate / Diploma", "Postgraduate"]],
            ["id" => "employment", "text" => "What is your current employment status?", "type" => "select", "options" => ["Student", "Employed full-time", "Employed part-time", "Self-employed", "Unemployed"]],
            ["id" => "marital", "text" => "What is your marital status?", "type" => "select", "options" => ["Single", "Married", "Divorced", "Widowed"]],
            ["id" => "family_income", "text" => "What is your family's income level?", "type" => "select", "options" => ["Low income", "Lower middle income", "Upper middle income", "High income"]],
            ["id" => "personal_income", "text" => "What is your personal monthly income range?", "type" => "select", "options" => ["No income", "Low income", "Lower middle income", "Middle income", "Upper middle income", "High income"]]
        ]
    ],
    [
        "id" => "environment", "title" => "Environment & Living Situation", "icon" => "house", "description" => "Information about where you live",
        "questions" => [
            ["id" => "residence", "text" => "Where do you currently live?", "type" => "select", "options" => ["Urban Area (with family)", "Urban Area (Hostel)", "Urban Area (in a hostel)", "Rural Area (Family)", "Rural Area (Alone)"]],
            ["id" => "province", "text" => "Which province of Sri Lanka do you currently reside in?", "type" => "select", "options" => ["Western Province", "Central Province", "Southern Province", "Northern Province", "Eastern Province", "North Western Province", "North Central Province", "Uva Province", "Sabaragamuwa Province"]],
            ["id" => "drug_prevalent", "text" => "Do you live in an area where drug use is prevalent?", "type" => "select", "options" => ["Yes", "No", "Not sure"]],
            ["id" => "drug_accessibility", "text" => "How accessible are drugs in your area?", "type" => "select", "options" => ["Not at all accessible", "Somewhat accessible", "Easily accessible"]],
            ["id" => "high_crime", "text" => "Do you live in a high-crime neighborhood?", "type" => "select", "options" => ["Yes", "No"]],
            ["id" => "living_with_user", "text" => "Are you currently living with a drug user?", "type" => "select", "options" => ["No", "Yes"]]
        ]
    ],
    [
        "id" => "relationships", "title" => "Relationships & Social Circle", "icon" => "people", "description" => "Information about your social connections",
        "questions" => [
            ["id" => "time_with", "text" => "Who do you spend most of your time with?", "type" => "select", "options" => ["Alone", "With family/relatives", "With friends", "Isolated/alone"]],
            ["id" => "daily_activity", "text" => "How do you spend most of your time daily? (Choose one that best describes your day)", "type" => "select", "options" => ["Studying", "Working", "Engaged in leisure (TV, internet, games, etc.)", "Isolated/alone", "All of the above"]],
            ["id" => "family_relationship", "text" => "How would you describe your relationship with your family?", "type" => "select", "options" => ["Very good", "Good", "Average", "Poor", "Very poor"]],
            ["id" => "family_substance", "text" => "Do you have a family history of substance abuse?", "type" => "select", "options" => ["Yes", "No", "Not sure"]],
            ["id" => "friends_drugs", "text" => "Do you have friends who use drugs?", "type" => "select", "options" => ["Yes", "No", "Not sure"]],
            ["id" => "family_addiction", "text" => "Do You Have an Addicted Person in Your Family?", "type" => "select", "options" => ["Yes", "No", "Not sure"]],
            ["id" => "close_friends", "text" => "Number of Close Friends", "type" => "select", "options" => ["None", "Few (1-4)", "Many (more than 5)"]],
            ["id" => "friends_influence", "text" => "Do Your Friends Influence Your Drug Use?", "type" => "select", "options" => ["No, they dont", "Sometimes", "Yes, often they do"]],
            ["id" => "nights_friends", "text" => "Do You Often Spend Nights at Friends Houses?", "type" => "select", "options" => ["No, I dont", "Sometimes", "Yes, often I do"]]
        ]
    ],
    [
        "id" => "substance", "title" => "Substance Use Patterns", "icon" => "exclamation-triangle", "description" => "Information about substance use",
        "questions" => [
            ["id" => "taken_drugs", "text" => "Ever taken drugs?", "type" => "select", "options" => ["Yes", "No"]],
            ["id" => "first_use_age", "text" => "At what age did you first use drugs?", "type" => "select", "options" => ["Not Applicable", "Under 18", "18-25", "26-35", "36+"]],
            ["id" => "substances", "text" => "Have you ever used any of the following substances? (Please select all that apply. If the drug youve used isnt listed, feel free to mention it.)", "type" => "checkbox", "options" => ["None", "Alcohol", "Marijuana", "Cocaine", "Heroin", "Methamphetamine", "Hashish", "Prescription drugs"]],
            ["id" => "frequency", "text" => "Which of the Following Best Describes Your Drug Use Frequency?", "type" => "select", "options" => ["Never used", "Occasionally", "Regularly", "Weekly"]],
            ["id" => "enhance_experience", "text" => "How many drugs are typically required to enhance the experience or achieve a feeling of being high? ", "type" => "select", "options" => ["Not Applicable", "One drug only", "Multiple drugs", "I have no idea", "Not sure"]],
            ["id" => "reason", "text" => "What was the main reason you tried drugs? ( Please select the main reason )", "type" => "checkbox", "options" => ["Not Applicable", "Peer pressure", "Social trend", "Curiosity", "Stress relief", "For chill", "Social stigma"]],
            ["id" => "external_factors", "text" => "Do You Believe External Factors (e.g., Peer or Family Pressure) Make You More Likely to Use Drugs?", "type" => "select", "options" => ["Yes", "No", "Not sure"]],
            ["id" => "relationship_affected", "text" => "Has drug use negatively affected your relationships (family, friends, colleagues)?", "type" => "select", "options" => ["Yes", "No"]],
            ["id" => "quit_plan", "text" => "Do You Have Plans or Desire to Quit drugs (if you do use)?", "type" => "select", "options" => ["Not applicable", "Yes, definitely", "Not yet decided", "No, I dont plan to quit"]],
            ["id" => "tobacco", "text" => "Do You Smoke Tobacco or Similar Products?", "type" => "select", "options" => ["No, I dont", "Yes, occasionally", "Yes, every day"]],
            ["id" => "try_new", "text" => "If Given the Chance, Would You Try or Taste a New Drug?", "type" => "select", "options" => ["No, I will not", "I dont know / Im confused", "Ill try"]]
        ]
    ],
    [
        "id" => "mental", "title" => "Mental Health & Behavior", "icon" => "emoji-neutral", "description" => "Mental health and behavioral patterns",
        "questions" => [
            ["id" => "trauma", "text" => "Have you experienced any traumatic events in your life?", "type" => "select", "options" => ["Yes", "No", "Prefer not to say"]],
            ["id" => "cravings", "text" => "Do you experience cravings for drugs?", "type" => "select", "options" => ["Yes", "No"]],
            ["id" => "withdrawal", "text" => "Have you ever experienced withdrawal symptoms from drug use?", "type" => "select", "options" => ["Yes", "No", "I have no idea"]],
            ["id" => "control", "text" => "Is It Easy to Control Your Drug Usage?", "type" => "select", "options" => ["Not applicable", "Yes, its possible", "Not sure", "No, its not possible"]],
            ["id" => "mental_problems", "text" => "Do You Experience Any of These Mental/Emotional Problems?", "type" => "checkbox", "options" => ["None", "Depression", "Anxiety", "Stress", "Tension", "Guilt", "Anger", "Low self-esteem", "PTSD"]],
            ["id" => "law_conflict", "text" => "Do You Ever Experience Conflict With the Law?", "type" => "select", "options" => ["No", "Yes"]],
            ["id" => "court_case", "text" => "Do You Have Any Case in Court?", "type" => "select", "options" => ["No", "Yes"]],
            ["id" => "failure", "text" => "Have You Ever Felt That You Failed in a Significant Aspect of Life?", "type" => "select", "options" => ["No", "Yes"]],
            ["id" => "suicidal", "text" => "Have You Ever Had Suicidal Thoughts?", "type" => "select", "options" => ["No", "Yes"]],
            ["id" => "satisfied", "text" => "Are you satisfied with your workplace/studies? ", "type" => "select", "options" => ["No", "Yes"]],
            ["id" => "motivation", "text" => "How motivated are you to stay sober or reduce your substance use? (110 scale)", "type" => "range", "min" => 1, "max" => 10]
        ]
    ]
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Substance Use Risk Assessment</title>
    <!-- Poppins font -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <!-- Bootstrap 5.3 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: #f8fafc;
            color: #222;
        }
        .assessment-container {
            max-width: 900px;
            margin: 2.5rem auto;
            background: #fff;
            border-radius: 1.5rem;
            box-shadow: 0 8px 32px rgba(80,80,120,0.07);
            overflow: hidden;
        }
        .header-section {
            background: linear-gradient(135deg, #6366f1, #4f46e5);
            color: #fff;
            padding: 2.5rem 2rem 2rem 2rem;
            border-radius: 1.5rem 1.5rem 0 0;
            text-align: center;
        }
        .header-section h1 {
            font-weight: 700;
            font-size: 2.2rem;
        }
        .progress {
            height: 6px;
            background: #e0e7ff;
        }
        .progress-bar {
            background: linear-gradient(90deg, #6366f1, #4f46e5);
        }
        .form-step {
            display: none;
            animation: fadeIn .55s cubic-bezier(.4,0,.2,1);
        }
        .form-step.active {
            display: block;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(24px);}
            to { opacity: 1; transform: translateY(0);}
        }
        .question-card {
            background: #f1f5f9;
            border-radius: 1rem;
            margin-bottom: 1.3rem;
            padding: 1.3rem 1.2rem;
            box-shadow: 0 2px 8px rgba(80,80,120,0.05);
            transition: box-shadow .2s;
        }
        .question-card:hover {
            box-shadow: 0 6px 20px rgba(80,80,120,0.10);
        }
        label.form-label {
            font-weight: 600;
            color: #374151;
        }
        .form-select, .form-control {
            border-radius: .7rem;
            font-size: 1.06rem;
        }
        .form-check-label {
            font-weight: 500;
            color: #374151;
        }
        .step-actions {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 1.5rem;
        }
        .btn {
            border-radius: 2rem;
            font-weight: 600;
            font-size: 1.08rem;
            transition: background .2s, box-shadow .2s;
        }
        .btn-primary {
            background: linear-gradient(90deg, #6366f1, #4f46e5);
            border: none;
        }
        .btn-primary:focus, .btn-primary:hover {
            background: linear-gradient(90deg, #4f46e5, #6366f1);
        }
        .btn-outline-secondary {
            border-radius: 2rem;
        }
        .fade-in {
            animation: fadeIn .7s cubic-bezier(.4,0,.2,1) both;
        }
        /* Result card styles */
        .result-card {
            border-radius: 1rem;
            padding: 2rem 1.5rem;
            margin-bottom: 1.5rem;
            color: #fff;
            position: relative;
            box-shadow: 0 4px 24px rgba(0,0,0,0.06);
        }
        .result-high { background: linear-gradient(135deg, #f87171, #dc2626);}
        .result-moderate { background: linear-gradient(135deg, #fbbf24, #d97706);}
        .result-low { background: linear-gradient(135deg, #34d399, #059669);}
        .result-icon {
            position: absolute;
            right: 1.2rem;
            top: 1.2rem;
            font-size: 3.5rem;
            opacity: 0.18;
        }
        .recommendation-card {
            background: #f1f5f9;
            color: #222;
            border-radius: 1rem;
            padding: 1.2rem 1rem;
            margin-top: 1.2rem;
            font-size: 1.03rem;
        }
        .recommendation-card i {
            color: #6366f1;
            margin-right: .7rem;
        }
        @media (max-width: 600px) {
            .assessment-container { border-radius: 0.7rem; }
            .header-section { border-radius: 0.7rem 0.7rem 0 0; padding: 1.5rem 1rem;}
            .question-card { padding: 1rem .6rem;}
        }
    </style>
</head>
<body>
<div class="container assessment-container shadow">
    <div class="header-section">
        <h1>Substance Use Risk Assessment</h1>
        <p class="mb-0">Confidential multi-step screening to understand your risk factors</p>
    </div>
    <div class="px-3 pt-3">
        <div class="progress mb-4">
            <div class="progress-bar" id="progressBar" style="width: 0%;"></div>
        </div>
        <form id="riskAssessmentForm" autocomplete="off">
            <?php foreach ($questionCategories as $stepIndex => $category): ?>
            <div class="form-step<?= $stepIndex === 0 ? ' active' : '' ?>" data-step="<?= $stepIndex ?>">
                <h4 class="mb-3"><i class="bi bi-<?= $category['icon'] ?>"></i> <?= $category['title'] ?></h4>
                <p class="text-secondary mb-4"><?= $category['description'] ?></p>
                <?php foreach ($category['questions'] as $question): ?>
                <div class="question-card fade-in">
                    <label class="form-label"><?= $question['text'] ?></label>
                    <?php if ($question['type'] === 'select'): ?>
                        <select class="form-select" name="<?= $question['id'] ?>" required>
                            <option value="" selected disabled>Select...</option>
                            <?php foreach ($question['options'] as $opt): ?>
                                <option value="<?= htmlspecialchars($opt) ?>"><?= htmlspecialchars($opt) ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php elseif ($question['type'] === 'checkbox'): ?>
                        <?php foreach ($question['options'] as $opt): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="<?= $question['id'] ?>[]" value="<?= htmlspecialchars($opt) ?>" id="<?= $question['id'] . '_' . md5($opt) ?>">
                                <label class="form-check-label" for="<?= $question['id'] . '_' . md5($opt) ?>"><?= htmlspecialchars($opt) ?></label>
                            </div>
                        <?php endforeach; ?>
                    <?php elseif ($question['type'] === 'range'): ?>
                        <input type="range" class="form-range" min="<?= $question['min'] ?>" max="<?= $question['max'] ?>" value="5" name="<?= $question['id'] ?>" id="<?= $question['id'] ?>-range" oninput="document.getElementById('<?= $question['id'] ?>-val').innerText=this.value;">
                        <div class="d-flex justify-content-between mt-1">
                            <span class="text-muted">Low</span>
                            <span id="<?= $question['id'] ?>-val" class="fw-bold">5</span>
                            <span class="text-muted">High</span>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                <div class="step-actions">
                    <?php if ($stepIndex > 0): ?>
                        <button type="button" class="btn btn-outline-secondary prev-step"><i class="bi bi-arrow-left"></i> Back</button>
                    <?php endif; ?>
                    <?php if ($stepIndex < count($questionCategories) - 1): ?>
                        <button type="button" class="btn btn-primary next-step">Continue <i class="bi bi-arrow-right"></i></button>
                    <?php else: ?>
                        <button type="submit" class="btn btn-primary pulse">Submit Assessment <i class="bi bi-send"></i></button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </form>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="resultsModal" tabindex="-1" aria-labelledby="resultsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius:1.2rem;">
      <div class="modal-header border-0">
        <h5 class="modal-title" id="resultsModalLabel">Risk Assessment Results</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="results-container">
        <div class="d-flex flex-column align-items-center py-4">
            <div class="spinner-border text-primary mb-3" role="status" style="width:2.5rem;height:2.5rem;">
                <span class="visually-hidden">Loading...</span>
            </div>
            <div class="text-secondary">Processing your responses...</div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Bootstrap JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const steps = document.querySelectorAll('.form-step');
const progressBar = document.getElementById('progressBar');
let currentStep = 0;

function updateProgressBar() {
    progressBar.style.width = ((currentStep+1)/steps.length*100) + "%";
}
updateProgressBar();

document.querySelectorAll('.next-step').forEach(btn => {
    btn.addEventListener('click', () => {
        // Validate current step
        const form = document.getElementById('riskAssessmentForm');
        let valid = true;
        steps[currentStep].querySelectorAll('select').forEach(sel => {
            if (!sel.value) {
                sel.classList.add('is-invalid');
                valid = false;
            } else {
                sel.classList.remove('is-invalid');
            }
        });
        if (!valid) return;
        steps[currentStep].classList.remove('active');
        currentStep++;
        steps[currentStep].classList.add('active');
        updateProgressBar();
        window.scrollTo({top:0,behavior:'smooth'});
    });
});
document.querySelectorAll('.prev-step').forEach(btn => {
    btn.addEventListener('click', () => {
        steps[currentStep].classList.remove('active');
        currentStep--;
        steps[currentStep].classList.add('active');
        updateProgressBar();
        window.scrollTo({top:0,behavior:'smooth'});
    });
});

// At the end of the RiskAssessmentForm submit event handler, replace the result display with:

document.getElementById('riskAssessmentForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('resultsModal'));
    document.getElementById('results-container').innerHTML = `
        <div class="d-flex flex-column align-items-center py-4">
            <div class="spinner-border text-primary mb-3" role="status" style="width:2.5rem;height:2.5rem;">
                <span class="visually-hidden">Loading...</span>
            </div>
            <div class="text-secondary">Processing your responses...</div>
        </div>
    `;
    modal.show();

    // Collect data
    const questionMap = <?= json_encode($questionIdToFeatureName) ?>;
    let data = {};
    document.querySelectorAll('select[name]').forEach(sel => {
        let qid = sel.name;
        let qtext = questionMap[qid] || qid;
        data[qtext] = sel.value;
    });
    document.querySelectorAll('input[type="range"]').forEach(rng => {
        let qid = rng.name;
        let qtext = questionMap[qid] || qid;
        data[qtext] = rng.value;
    });
    // Checkboxes (multi)
    Object.keys(questionMap).forEach(qid => {
        let boxes = document.querySelectorAll('input[name="'+qid+'[]"]:checked');
        if (boxes.length) {
            let qtext = questionMap[qid] || qid;
            data[qtext] = Array.from(boxes).map(cb=>cb.value).join(';');
        }
    });

    // API call
    try {
        const res = await fetch('http://localhost:5000/predict', {
            method: 'POST',
            headers: {'Content-Type':'application/json','Accept':'application/json'},
            body: JSON.stringify(data)
        });
        if (!res.ok) throw new Error(await res.text());
        const result = await res.json();
        
        console.log("API Result:", result); // Debug log
        
        // Save assessment data to database
        const saveRes = await fetch('processes/save_assessment.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({
                risk_level: result.risk_stage,
                questionnaire_data: data
            })
        });

        
        const saveResult = await saveRes.json();
        console.log("Save Result:", saveResult); // Debug log
        
        if (!saveRes.ok) throw new Error(saveResult.message || 'Failed to save assessment');
        
        // Show thank you message instead of results
        document.getElementById('results-container').innerHTML = `
            <div class="text-center py-4">
                <div class="mb-4">
                    <i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i>
                </div>
                <h3 class="mb-3">Thank You for Completing the Assessment</h3>
                <p class="mb-4">Your responses have been submitted successfully. A counsellor will review your assessment and may contact you to discuss the results.</p>
                <a href="UserDashboard.php" class="btn btn-primary">Return to Home</a>
            </div>
        `;
    } catch (error) {
        console.error("Error:", error); // Debug log
        document.getElementById('results-container').innerHTML = `
            <div class="text-center py-4">
                <div class="mb-4">
                    <i class="bi bi-x-circle-fill text-danger" style="font-size: 4rem;"></i>
                </div>
                <h3 class="mb-3">Something Went Wrong</h3>
                <p class="mb-4">We couldn't process your assessment. Please try again later or contact support.</p>
                <p class="text-danger">${error.message}</p>
                <a href="UserDashboard.php" class="btn btn-primary">Return to Home</a>
            </div>
        `;
    }
});

</script>
</body>
</html>