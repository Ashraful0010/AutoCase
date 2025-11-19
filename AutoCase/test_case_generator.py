"""
AI Test Case Generator - Standalone Version WITH DOCX SUPPORT
"""

import sys
import os
import pandas as pd
import numpy as np
import spacy
from spacy.matcher import Matcher
import nltk
import re
import warnings
import docx   
import matplotlib.pyplot as plt
import seaborn as sns
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.ensemble import RandomForestClassifier
from sklearn.model_selection import train_test_split
from sklearn.metrics import classification_report

warnings.filterwarnings('ignore')

# ===============================
# DOCX → CSV CONVERTER (NEW)
# ===============================

def convert_docx_to_csv(docx_path):
    print("📄 Converting DOCX → CSV...")

    document = docx.Document(docx_path)

    rows = []
    req_counter = 1

    for para in document.paragraphs:
        text = para.text.strip()
        if not text:
            continue

        # Match pattern: R1: Something...
        match = re.match(r"^(R\d+)\s*:\s*(.+)$", text)
        if match:
            req_id = match.group(1)
            req_text = match.group(2)

            rows.append({
                "requirement_id": req_id,
                "requirement_text": req_text,
                "priority": "Medium",     # default
                "category": "general"     # default
            })

    if not rows:
        print("❌ No valid requirement pattern (R#: text) found in DOCX")
        sys.exit(1)

    df = pd.DataFrame(rows)

    out_csv = "converted_requirements.csv"
    df.to_csv(out_csv, index=False)

    print("✅ DOCX converted successfully →", out_csv)
    return out_csv


# ===============================
# CHECK INPUT & AUTO-CONVERT DOCX
# ===============================

if len(sys.argv) < 2:
    print("❌ Error: No input file provided.")
    sys.exit(1)

input_file = sys.argv[1]

# Auto-detect and convert DOCX
if input_file.endswith(".docx"):
    print("📥 DOCX detected. Converting to CSV...")
    input_file = convert_docx_to_csv(input_file)   # replace path with CSV

# Make sure outputs/ exists
os.makedirs("outputs", exist_ok=True)

# Ensure NLTK data exists
nltk.download('punkt', quiet=True)
nltk.download('stopwords', quiet=True)
nltk.download('averaged_perceptron_tagger', quiet=True)
nltk.download('vader_lexicon', quiet=True)

# Load spaCy model
try:
    nlp = spacy.load("en_core_web_sm")
except:
    from spacy.cli import download
    download("en_core_web_sm")
    nlp = spacy.load("en_core_web_sm")

# ===============================
# CLASSES
# ===============================

class RequirementsProcessor:
    """Process software requirements from CSV/Excel files"""

    def __init__(self):
        self.nlp = nlp
        self.stop_words = set(nltk.corpus.stopwords.words('english'))
        
        # Setup spaCy Matcher
        self.matcher = Matcher(nlp.vocab)
        
        # Define patterns for "Actor -> Action -> Object"
        self.matcher.add("ACTOR_ACTION_OBJECT", [
            [{"POS": {"IN": ["NOUN", "PROPN"]}, "DEP": "nsubj"}],  # Actor
            [{"POS": "VERB", "DEP": "ROOT"}],                     # Action
            [{"POS": {"IN": ["NOUN", "PROPN"]}, "DEP": "dobj"}]    # Object
        ])
        
        self.matcher.add("ACTOR_ACTION_OBJECT_MODAL", [
            [{"POS": {"IN": ["NOUN", "PROPN"]}, "DEP": "nsubj"}],  # Actor
            [{"POS": "AUX", "DEP": "aux"}],                       # e.g., "should"
            [{"POS": "AUX", "DEP": "aux"}],                       # e.g., "be able to"
            [{"POS": "VERB"}],                                    # Action
            [{"POS": {"IN": ["NOUN", "PROPN"]}, "DEP": "dobj"}]    # Object
        ])

    def load_requirements(self, file_path):
        if file_path.endswith('.csv'):
            df = pd.read_csv(file_path)
        elif file_path.endswith(('.xlsx', '.xls')):
            df = pd.read_excel(file_path)
        else:
            raise ValueError("Unsupported file format. Use CSV or Excel.")
        
        if 'requirement_text' not in df.columns:
            print(f"Warning: 'requirement_text' column not found. Using first column: {df.columns[0]}")
            df.rename(columns={df.columns[0]: 'requirement_text'}, inplace=True)
            
        return df

    def preprocess_text(self, text):
        if pd.isna(text):
            return ""
        text = re.sub(r'\s+', ' ', str(text)).strip()
        return text

    def extract_entities(self, text):
        doc = self.nlp(text)
        entities = {
            'actors': [],
            'actions': [],
            'objects': [],
            'conditions': []
        }

        # 1. Use Matcher
        matches = self.matcher(doc)
        for match_id, start, end in matches:
            span = doc[start:end]
            for token in span:
                if token.dep_ == "nsubj":
                    entities['actors'].append(token.text)
                elif token.pos_ == "VERB" and token.dep_ == "ROOT":
                    entities['actions'].append(token.lemma_)
                elif token.dep_ == "dobj":
                    entities['objects'].append(token.text)

        # 2. Fallback: Use POS tags
        if not entities['actors']:
            for token in doc:
                if token.dep_ == "nsubj" and token.pos_ in ["NOUN", "PROPN"]:
                    entities['actors'].append(token.text)
        if not entities['actions']:
            for token in doc:
                if token.pos_ == "VERB" and token.dep_ == "ROOT":
                    entities['actions'].append(token.lemma_)
        if not entities['objects']:
            for token in doc:
                if token.dep_ == "dobj" and token.pos_ in ["NOUN", "PROPN"]:
                    entities['objects'].append(token.text)
                    
        # 3. Find Conditions
        for word in ['if', 'when', 'while', 'unless', 'provided', 'given']:
            if word in text.lower():
                entities['conditions'].append(word)

        # Clean up duplicates
        entities['actors'] = list(set(entities['actors']))
        entities['actions'] = list(set(entities['actions']))
        entities['objects'] = list(set(entities['objects']))
        entities['conditions'] = list(set(entities['conditions']))
        
        return entities


class TestScenarioGenerator:
    """Generate test scenarios using rule-based + ML approach"""

    def __init__(self):
        self.vectorizer = TfidfVectorizer(max_features=1000, stop_words='english')
        self.classifier = RandomForestClassifier(n_estimators=100, random_state=42)

    def create_training_data(self, df):
        test_categories = ['functional_test', 'boundary_test', 'negative_test', 'performance_test', 'security_test']
        training_data = []
        for _, req in df.iterrows():
            text = req.get('requirement_text', '')
            if pd.isna(text): continue
            
            if any(w in text.lower() for w in ['login', 'authenticate', 'password', 'permission', 'unauthorized']):
                category = 'security_test'
            elif any(w in text.lower() for w in ['performance', 'speed', 'load', 'concurrent', 'response time']):
                category = 'performance_test'
            elif any(w in text.lower() for w in ['invalid', 'error', 'exception', 'fail', 'wrong']):
                category = 'negative_test'
            elif any(w in text.lower() for w in ['maximum', 'minimum', 'limit', 'boundary', 'range']):
                category = 'boundary_test'
            else:
                category = 'functional_test'
            training_data.append({'text': text, 'category': category})
        return pd.DataFrame(training_data)

    def train_model(self, training_data):
        if training_data.empty:
            print("Warning: No training data to train model.")
            return
        X = self.vectorizer.fit_transform(training_data['text'])
        y = training_data['category']
        
        if len(set(y)) < 2:
            print(f"Warning: Only one class ('{y[0]}') found. Model will not be trained.")
            class DummyClassifier:
                def __init__(self, category): self.category = category
                def fit(self, X, y): pass
                def predict(self, X): return [self.category] * X.shape[0]
                def predict_proba(self, X): return [[1.0]] * X.shape[0]
            self.classifier = DummyClassifier(y[0] if len(y) > 0 else 'functional_test')
            return

        X_train, X_test, y_train, y_test = train_test_split(X, y, test_size=0.2, random_state=42, stratify=y)
        self.classifier.fit(X_train, y_train)
        y_pred = self.classifier.predict(X_test)
        print("\n📊 Model Performance Summary:")
        print(classification_report(y_test, y_pred, zero_division=0))

    def generate_test_scenarios(self, requirement_text, entities):
        X = self.vectorizer.transform([requirement_text])
        category = self.classifier.predict(X)[0]
        try:
            confidence = max(self.classifier.predict_proba(X)[0])
        except AttributeError:
            confidence = 1.0

        return {
            'requirement': requirement_text,
            'test_category': category,
            'confidence': confidence,
            'scenarios': self._generate_scenarios(category, entities),
            'entities': entities
        }

    def _generate_scenarios(self, category, entities):
        def get_entity(key, default="[component]"):
            val = entities.get(key)
            return val[0] if val else default
        actor = get_entity('actors', default="The user")
        action = get_entity('actions', default="perform the action")
        obj = get_entity('objects', default="the feature")
        condition = get_entity('conditions', default="")

        scenarios = []
        if category == 'functional_test':
            scenarios = [
                f"Verify that the {actor} can successfully {action} the {obj}.",
                f"Test the primary workflow for {action} the {obj}.",
                f"Check that the correct output is displayed after the {actor} completes {action}.",
                f"Verify all UI elements related to {obj} are present and functional.",
                f"Test alternative paths for the {actor} to {action} the {obj}."
            ]
        elif category == 'negative_test':
            scenarios = [
                f"Test what happens if the {actor} tries to {action} with an invalid {obj}.",
                f"Verify error message when {actor} provides missing data for {action}.",
                f"Test system behavior if the {actor} cancels the {action} mid-workflow.",
                f"Test submitting malformed data when {actor} tries to {action} the {obj}.",
                f"Verify that the {actor} cannot {action} the {obj} without proper permissions."
            ]
        elif category == 'boundary_test':
            scenarios = [
                f"Test {action} with the minimum allowed value for {obj}.",
                f"Test {action} with the maximum allowed value for {obj}.",
                f"Test {action} with a value just below the minimum for {obj}.",
                f"Test {action} with a value just above the maximum for {obj}.",
                f"Test {action} with a typical or average value for {obj}."
            ]
        elif category == 'security_test':
            scenarios = [
                f"Verify that an unauthorized {actor} cannot {action} the {obj}.",
                f"Test for SQL injection vulnerabilities in input fields related to {obj}.",
                f"Verify {action} requires proper authentication.",
                f"Test session management when the {actor} performs {action}.",
                f"Check that sensitive data related to {obj} is masked or encrypted."
            ]
        elif category == 'performance_test':
             scenarios = [
                f"Measure the response time for the {actor} to {action} the {obj} under normal load.",
                f"Test system load when 100 concurrent {actor}s try to {action} the {obj}.",
                f"Verify that the {action} completes within the 3-second performance SLA.",
                f"Measure system resource (CPU, memory) usage during the {action}.",
                f"Test how the system handles sustained load while {actor}s repeatedly {action} the {obj}."
            ]
        if condition:
            scenarios.append(f"Test conditional logic: Verify {action} {condition} the condition is met.")
        return scenarios[:5]


class TestCaseGenerator:
    """Generate detailed test cases including test steps"""

    def __init__(self):
        self.test_cases = []

    # ⭐ --- UPDATED FUNCTION --- ⭐
    def generate_test_cases(self, scenarios_list):
        all_cases = []
        for i, scenario_data in enumerate(scenarios_list):
            # Get the entities back out
            entities = scenario_data.get('entities', {}) 
            category = scenario_data['test_category'] # <-- Get the category
            
            for j, scenario in enumerate(scenario_data['scenarios']):
                all_cases.append({
                    'test_id': f"TC_{i+1}_{j+1}",
                    'requirement_id': f"REQ_{i+1}",
                    'test_name': f"{category.capitalize()} Test {j+1}",
                    'test_description': scenario,
                    'test_category': category,
                    'priority': self._priority(scenario_data['confidence']),
                    # ⭐ PASS THE CATEGORY to both functions ⭐
                    'test_steps': self._generate_test_steps(scenario, entities, category),
                    'expected_result': self._generate_expected_result(scenario, entities, category),
                    'confidence_score': round(scenario_data['confidence'], 2)
                })
        self.test_cases = all_cases
        return all_cases

    def _priority(self, conf):
        if conf >= 0.8: return 'High'
        elif conf >= 0.6: return 'Medium'
        else: return 'Low'

    # ⭐ --- NEW DYNAMIC VERSION --- ⭐
    def _generate_test_steps(self, scenario, entities, category):
        """Generate category-specific, dynamic test steps"""
        
        actor = entities.get('actors', ['user'])[0] if entities.get('actors') else 'user'
        obj = entities.get('objects', ['page/feature'])[0] if entities.get('objects') else 'page/feature'
        action = entities.get('actions', ['action'])[0] if entities.get('actions') else 'action'

        steps_template = {
            'functional_test': [
                f"1. Navigate to the {obj} where the {action} can be performed.",
                f"2. As a(n) {actor}, provide all necessary valid data to perform the {action}.",
                f"3. Initiate the {action} (e.g., click 'Submit', 'Save', or 'Run').",
                f"4. Observe the system's response.",
                f"5. Verify that the system behaves as described in the scenario: '{scenario}'"
            ],
            'negative_test': [
                f"1. Navigate to the {obj}.",
                f"2. As a(n) {actor}, attempt to perform the {action} using invalid, malformed, or incomplete data.",
                f"3. For example: {scenario}",
                f"4. Initiate the {action}.",
                f"5. Observe the system's response.",
                f"6. Verify that a clear, user-friendly error message is displayed and the system remains stable."
            ],
            'boundary_test': [
                f"1. Navigate to the input field on {obj} related to the boundary.",
                f"2. As a(n) {actor}, enter the specific boundary value described in the scenario.",
                f"3. For example: {scenario}",
                f"4. Attempt to submit or {action} with this value.",
                f"5. Observe the system's response.",
                f"6. Verify the system correctly accepts or rejects the value as expected."
            ],
            'security_test': [
                f"1. As an {actor} (e.g., an unauthorized user, or user with wrong role), navigate to {obj}.",
                f"2. Attempt to perform the privileged {action} as described: '{scenario}'",
                f"3. Observe all system responses, including UI messages and network responses (if possible).",
                f"4. Verify that the action is blocked and an 'Unauthorized' or 'Access Denied' error is shown.",
                f"5. (If possible) Check system logs to ensure the unauthorized attempt was recorded."
            ],
            'performance_test': [
                f"1. Set up the test environment (e.g., load testing tool, baseline data).",
                f"2. Configure the test to simulate the scenario: '{scenario}'",
                f"3. (If load test) Begin with a single {actor} to establish a baseline response time for {action}.",
                f"4. Gradually increase the load to the target number of concurrent {actor}s.",
                f"5. Measure the response time, throughput, and system resource (CPU, Memory) usage.",
                f"6. Verify all metrics remain within the defined performance SLAs."
            ]
        }
        
        # Get the template for the category, or fall back to 'functional' if unknown
        return "\n".join(steps_template.get(category, steps_template['functional_test']))

    def _generate_expected_result(self, scenario, entities, category):
        actor = entities.get('actors', ['user'])[0] if entities.get('actors') else 'user'
        obj = entities.get('objects', ['feature'])[0] if entities.get('objects') else 'feature'
        action = entities.get('actions', ['action'])[0] if entities.get('actions') else 'action'

        if category == 'negative_test':
            return f"The system should prevent the {actor} from completing the {action} and display a clear, user-friendly error message."
        elif category == 'security_test':
            return f"The system should block the unauthorized {action} and log the security attempt. The {actor} should not gain access to {obj}."
        elif category == 'performance_test':
            return f"The {action} should complete within the defined performance SLA (e.g., under 3 seconds) and the system should remain stable."
        else: # Functional, Boundary
            return f"The {actor} should be able to complete the {action} successfully. The state of the {obj} should be updated correctly as described in the requirement."

    def calculate_metrics(self, df):
        total_reqs = len(df)
        if total_reqs == 0:
            return {'requirements_coverage': 0, 'total_test_cases': 0, 'category_distribution': {}}
        covered_reqs = len(set(tc['requirement_id'] for tc in self.test_cases))
        coverage = (covered_reqs / total_reqs) * 100 if total_reqs else 0
        categories = {}
        for tc in self.test_cases:
            cat = tc['test_category']
            categories[cat] = categories.get(cat, 0) + 1
        return {'requirements_coverage': coverage, 'total_test_cases': len(self.test_cases), 'category_distribution': categories}


# ===============================
# VISUALIZATION
# ===============================

def visualize_results(test_cases, metrics):
    os.makedirs("outputs", exist_ok=True)
    
    # 1. Category distribution
    category_counts = metrics.get('category_distribution', {})
    if category_counts:
        plt.figure(figsize=(8, 5))
        sns.barplot(x=list(category_counts.keys()), y=list(category_counts.values()), palette='viridis')
        plt.title('Test Case Distribution by Category')
        plt.xlabel('Test Category')
        plt.ylabel('Number of Test Cases')
        plt.tight_layout()
        plt.savefig('outputs/category_distribution.png')
        plt.close()

    # 2. Priority distribution
    priorities = {'High': 0, 'Medium': 0, 'Low': 0}
    for tc in test_cases:
        priorities[tc['priority']] = priorities.get(tc['priority'], 0) + 1
    if sum(priorities.values()) > 0:
        plt.figure(figsize=(5, 5))
        plt.pie(priorities.values(), labels=priorities.keys(), autopct='%1.1f%%', startangle=140, colors=sns.color_palette('pastel'))
        plt.title('Test Case Distribution by Priority')
        plt.axis('equal')
        plt.tight_layout()
        plt.savefig('outputs/priority_distribution.png')
        plt.close()

    # 3. Coverage
    coverage = metrics.get('requirements_coverage', 0)
    plt.figure(figsize=(6, 3))
    plt.barh(['Requirements Coverage'], [coverage], color='skyblue')
    plt.xlim(0, 100)
    plt.xlabel('Coverage (%)')
    plt.title('Requirements Coverage')
    plt.text(coverage + 1, 0, f"{coverage:.1f}%", va='center', fontweight='bold')
    plt.tight_layout()
    plt.savefig('outputs/coverage_chart.png')
    plt.close()


# ===============================
# MAIN PIPELINE
# ===============================

def main_pipeline(file_path):
    print("🚀 Starting Test Case Generation")
    print("=" * 60)
    try:
        processor = RequirementsProcessor()
        df = processor.load_requirements(file_path)
    except FileNotFoundError:
        print(f"❌ Error: Input file not found at '{file_path}'")
        sys.exit(1)
    except Exception as e:
        print(f"❌ Error loading file: {e}")
        sys.exit(1)

    df.dropna(subset=['requirement_text'], inplace=True)
    df = df[df['requirement_text'].str.strip() != '']
    if df.empty:
        print("❌ Error: The input file is empty or contains no valid requirements.")
        sys.exit(1)
        
    df['processed_text'] = df['requirement_text'].apply(processor.preprocess_text)
    df['entities'] = df['processed_text'].apply(processor.extract_entities)

    print(f"✅ Loaded {len(df)} requirements and extracted entities")
    scenario_gen = TestScenarioGenerator()
    training_data = scenario_gen.create_training_data(df)
    scenario_gen.train_model(training_data)
    scenarios = [
        scenario_gen.generate_test_scenarios(row['processed_text'], row['entities']) 
        for _, row in df.iterrows()
    ]
    print(f"✅ Generated scenarios for {len(scenarios)} requirements")

    test_gen = TestCaseGenerator()
    test_cases = test_gen.generate_test_cases(scenarios)
    metrics = test_gen.calculate_metrics(df)
    output_file = os.path.join("outputs", "generated_test_cases.csv")
    pd.DataFrame(test_cases).to_csv(output_file, index=False)

    print("=" * 60)
    print("📈 COVERAGE SUMMARY")
    print("=" * 60)
    print(f"Requirements Coverage: {metrics['requirements_coverage']:.1f}%")
    print(f"Total Test Cases: {metrics['total_test_cases']}")
    
    if metrics['category_distribution']:
        print("\nTest Category Distribution:")
        for cat, count in metrics['category_distribution'].items():
            print(f" - {cat}: {count}")

    visualize_results(test_cases, metrics)
    print("\n📊 Charts and CSV saved to 'outputs/' folder")
    return output_file, metrics


# ===============================
# RUN SCRIPT
# ===============================

if __name__ == "__main__":
    file_path = input_file
    output_file, metrics = main_pipeline(file_path)
    
    print("\n✅ Test Case Generation Completed Successfully.")
    print(f"Output File: {output_file}")
    print(f"Total Test Cases: {metrics['total_test_cases']}")
    print(f"Requirements Coverage: {metrics['requirements_coverage']:.1f}%")
