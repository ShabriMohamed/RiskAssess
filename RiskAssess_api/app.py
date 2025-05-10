from flask import Flask, request, jsonify
import pandas as pd
import numpy as np
import joblib
from flask_cors import CORS
import os

app = Flask(__name__)
CORS(app)  # Allow all origins

# Load the saved model and components
model_path = 'drug_addiction_prediction_model.pkl'
inference_components = joblib.load(model_path)

model = inference_components['model']
pipeline = inference_components['pipeline']
label_encoders = inference_components['label_encoders']
target_encoder = inference_components['target_encoder']

@app.route('/predict', methods=['POST'])
def predict():
    try:
        # Get data from request
        data = request.get_json()
        input_df = pd.DataFrame([data])

        # Preprocess input data
        processed_input = preprocess_input(input_df)

        # Make prediction
        risk_stage_encoded = model.predict(processed_input)[0]
        risk_probabilities = model.predict_proba(processed_input)[0]
        risk_stage = target_encoder.inverse_transform([risk_stage_encoded])[0]

        high_risk_idx = 2  # Index for 'High' risk class (assuming 0=Low, 1=Moderate, 2=High)
        addiction_likelihood = risk_probabilities[high_risk_idx] * 100

        stage_from_likelihood = "Low"
        if addiction_likelihood > 33 and addiction_likelihood <= 80:
            stage_from_likelihood = "Moderate"
        elif addiction_likelihood > 80:
            stage_from_likelihood = "High"

        return jsonify({
            'risk_stage': risk_stage,
            
        })

    except Exception as e:
        return jsonify({'error': str(e)}), 400

def preprocess_input(input_df):
    # Multi-choice columns as in your CSV/model
    multi_choice_columns = [
        "Do You Experience Any of These Mental/Emotional Problems?",
        "Have you ever used any of the following substances? (Please select all that apply. If the drug youve used isnt listed, feel free to mention it.)",
        "What was the main reason you tried drugs? ( Please select the main reason )"
    ]

    # Process multiple-choice fields
    for col in multi_choice_columns:
        if col in input_df.columns:
            input_df = process_multiple_choice_input(input_df, col)

    # Encode categorical features
    for col in input_df.select_dtypes(include=['object']).columns:
        if col in label_encoders:
            le = label_encoders[col]
            input_df[col] = input_df[col].map(lambda x: x if x in le.classes_ else le.classes_[0])
            input_df[col] = le.transform(input_df[col])

    # Convert to numeric and fill missing values
    input_df = input_df.apply(pd.to_numeric, errors='coerce')
    input_df = input_df.fillna(0)

    # --- Robust feature alignment ---
    # Ensure all expected columns are present (add missing as 0)
    expected_cols = list(pipeline.feature_names_in_)
    for col in expected_cols:
        if col not in input_df.columns:
            input_df[col] = 0
    # Drop any extra columns
    input_df = input_df[expected_cols]

    # Apply preprocessing pipeline
    processed_input = pipeline.transform(input_df)
    return processed_input

def process_multiple_choice_input(df, column_name):
    """Process multiple-choice inputs from API request"""
    if column_name not in df.columns:
        return df
    choices = df[column_name].iloc[0]
    if pd.isna(choices):
        choices = ""
    # Find columns in the training data that were created from this multiple-choice field
    prefix = f"{column_name}__"
    for col in pipeline.feature_names_in_:
        if col.startswith(prefix):
            option = col[len(prefix):]
            df[col] = 1 if option in [c.strip() for c in choices.split(';')] else 0
    df = df.drop(columns=[column_name])
    return df

@app.route('/health', methods=['GET'])
def health_check():
    return jsonify({'status': 'healthy', 'model_loaded': model is not None})

if __name__ == '__main__':
    app.run(debug=True, host='0.0.0.0', port=5000)
