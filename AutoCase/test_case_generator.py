"""
AI Test Case Generator - Standalone Version WITH DOCX SUPPORT
"""
import random
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
        
        # --- START OF FIX: Check for single-member classes before stratified split ---
        unique_classes = y.value_counts()
        # Check if all classes have at least 2 samples
        if (unique_classes >= 2).all():
            use_stratify = True
        else:
            use_stratify = False
            print(f"⚠️ Warning: Some classes have only 1 member ({unique_classes[unique_classes == 1].index.tolist()}). Falling back to non-stratified split.")
        
        if len(set(y)) < 2:
            print(f"Warning: Only one class ('{y.iloc[0]}') found. Model will not be trained.")
            class DummyClassifier:
                def __init__(self, category): self.category = category
                def fit(self, X, y): pass
                def predict(self, X): return [self.category] * X.shape[0]
                def predict_proba(self, X): return [[1.0]] * X.shape[0]
            self.classifier = DummyClassifier(y.iloc[0] if len(y) > 0 else 'functional_test')
            return

        if use_stratify:
            # ⭐ CHANGED test_size from 0.2 to 0.3 to meet the minimum requirement of 5 samples (20 * 0.3 = 6 samples) ⭐
            X_train, X_test, y_train, y_test = train_test_split(X, y, test_size=0.3, random_state=42, stratify=y)
        else:
            # Fallback to non-stratified split, avoiding the ValueError, also using 0.3 for consistency
            X_train, X_test, y_train, y_test = train_test_split(X, y, test_size=0.3, random_state=42)
        # --- END OF FIX ---

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

    # ⭐ --- MODIFIED FUNCTION --- ⭐
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
                    # ⭐ NEW COLUMN ADDED HERE ⭐
                    'preconditions': self._generate_preconditions(scenario, entities, category), 
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

    # ⭐ --- NEW DYNAMIC PRECONDITION FUNCTION --- ⭐
    def _generate_preconditions(self, scenario, entities, category):
        """Generate category-specific preconditions, emphasizing entity context."""
        
        actor = entities.get('actors', ['user'])[0] if entities.get('actors') else 'user'
        obj = entities.get('objects', ['feature'])[0] if entities.get('objects') else 'feature'
        action = entities.get('actions', ['action'])[0] if entities.get('actions') else 'action'
        condition = entities.get('conditions', [''])[0] if entities.get('conditions') else ''
        
        # Base preconditions are now more dynamic
        base_preconditions = [
            "1. The system is operational and accessible.",
            f"2. The test environment has the necessary configuration for '{obj}'."
        ]
        
        preconditions = []
        
        if category in ['functional_test', 'boundary_test', 'negative_test']:
            preconditions = base_preconditions + [
                f"3. The {actor} account is active and has permissions to '{action}' the {obj}.",
                f"4. Required data for {obj} is pre-populated in the database/system."
            ]
        elif category == 'security_test':
             preconditions = base_preconditions + [
                f"3. An *unauthorized* user account (without access to {obj}) is available.",
                f"4. Authentication/Authorization services are fully operational."
            ]
        elif category == 'performance_test':
            preconditions = [
                "1. A dedicated Performance Test environment is available and stable.",
                f"2. Load generation tools are configured to simulate concurrent users {action} the {obj}.",
                "3. Monitoring tools (CPU, memory) are active to capture metrics."
            ]
        
        # Inject condition logic at the end if present
        if condition:
            preconditions.append(f"State Requirement: The system must meet the triggering condition for the {action} to be attempted.")

        # Ensure the final list includes the base in case of other categories
        if not preconditions:
            preconditions = base_preconditions

        return "\n".join(preconditions)


    # ⭐ --- UPDATED: DYNAMIC & VARIED TEST STEPS --- ⭐
    def _generate_test_steps(self, scenario, entities, category):
        """Generate dynamic test steps with increased randomization and keyword context."""
        
        actor = entities.get('actors', ['user'])[0] if entities.get('actors') else 'user'
        obj = entities.get('objects', ['feature'])[0] if entities.get('objects') else 'feature'
        action = entities.get('actions', ['perform action'])[0] if entities.get('actions') else 'perform action'
        condition = entities.get('conditions', [''])[0] if entities.get('conditions') else ''

        # --- CONTEXT DETECTION ---
        # Detect specific activities to swap templates
        text_context = scenario.lower() + " " + action.lower()
        is_upload = any(x in text_context for x in ['upload', 'attach', 'file', 'image', 'document'])
        is_search = any(x in text_context for x in ['search', 'find', 'query', 'filter', 'sort'])
        is_login = any(x in text_context for x in ['login', 'sign in', 'log in', 'auth'])
        is_form = any(x in text_context for x in ['submit', 'fill', 'enter', 'form', 'create', 'update'])

        steps = []

        # 1. SETUP STEP (Increased Variations)
        setup_variations = [
            f"1. As {actor}, launch the application and navigate to the primary dashboard.",
            f"1. Navigate directly to the '{obj}' interface/page.",
            f"1. From the main menu, select the option to {action} the {obj}.",
            f"1. Ensure the session for {actor} is active and ready."
        ]
        steps.append(random.choice(setup_variations))

        # 2. ACTION STEPS (More Granular and Context Aware)
        if category == 'functional_test':
            if is_login:
                steps.extend([
                    "2. Enter valid credentials (username and password).",
                    "3. Click the 'Login' or 'Sign In' button.",
                    "4. Verify the successful redirection."
                ])
            elif is_upload:
                steps.extend([
                    f"2. Click the upload button associated with {obj}.",
                    "3. Select a file of the required type and size (valid input).",
                    "4. Monitor the upload progress and confirmation message."
                ])
            elif is_search:
                steps.extend([
                    f"2. Enter a known, valid search term into the search field.",
                    f"3. Execute the search/filter operation.",
                    f"4. Review the search results list."
                ])
            elif is_form:
                steps.extend([
                    "2. Fill in all fields with standard, valid data.",
                    "3. Click the 'Save' or 'Submit' button.",
                    f"4. Verify the new or updated {obj} record appears correctly."
                ])
            else:
                # Generic Functional
                steps.extend([
                    f"2. Execute the primary action: '{action}'.",
                    f"3. Review any immediate feedback or system changes.",
                    f"4. Check related modules for data consistency."
                ])

        elif category == 'negative_test':
            # Negative steps are more action-specific
            if is_login:
                bad_input = "an incorrect password"
            elif is_form:
                bad_input = "a value outside the allowed data type (e.g., text in a number field)"
            elif is_upload:
                 bad_input = "a file type that is explicitly not allowed (e.g., an .exe)"
            else:
                bad_input = "a known bad/malformed input"
            
            steps.extend([
                f"2. Attempt to {action} by providing {bad_input}.",
                "3. Observe the system's reaction.",
                "4. Check for the display of an informative error message that guides the user.",
                "5. Verify the state of the system remains unchanged (data was not saved)."
            ])

        elif category == 'security_test':
            # Security steps are now more focused on privilege and direct attacks
            if "unauthorized" in scenario.lower() or "permission" in scenario.lower():
                steps.extend([
                    "2. Log in with the *unauthorized* user account.",
                    f"3. Attempt to access or {action} the {obj}.",
                    "4. Verify a denial of service or access error is returned."
                ])
            elif "injection" in scenario.lower():
                steps.extend([
                    "2. In an input field, enter a common injection payload (e.g., XSS or SQL).",
                    "3. Submit the input.",
                    "4. Verify the system sanitizes or rejects the input, and no malicious code executes."
                ])
            else:
                steps.extend([
                    f"2. Perform a session-related manipulation (e.g., check for broken access control via URL tampering).",
                    f"3. Attempt to {action} the {obj} using the manipulated session.",
                    "4. Confirm that the security control properly enforces the policy."
                ])

        elif category == 'performance_test':
            steps.extend([
                "2. Begin the load/stress test simulation.",
                f"3. Execute the {action} under a predefined concurrent load (e.g., 50 users).",
                "4. Record the average and peak response times using monitoring tools.",
                "5. Check the application/database logs for performance degradation or errors under load."
            ])
        
        elif category == 'boundary_test':
             steps.extend([
                f"2. Input the value representing the *minimum accepted boundary* for {obj}.",
                "3. Attempt to submit the form/action.",
                f"4. Repeat with a value *just below* the minimum boundary.",
                "5. Verify that the minimum boundary is accepted and the sub-minimum value is rejected."
            ])
             # Add a final step for the maximum boundary
             steps.append("6. Repeat steps 2-5 for the maximum accepted boundary value (+1 over max).")
        
        # 3. VERIFICATION STEP (Direct reference to scenario)
        verification_variations = [
            f"Last. Verify the final result is: '{scenario}' is true.",
            f"Last. Confirm the data state of {obj} reflects a successful and accurate {action}.",
            f"Last. Check system logs/audit trail to ensure the transaction for {action} was recorded correctly."
        ]
        steps.append(random.choice(verification_variations))

        # Re-number the steps
        final_steps = []
        for i, step in enumerate(steps):
            # Check if step is the 'Last.' step to keep it
            if step.startswith('Last.'):
                final_steps.append(f"{i+1}. {step[5:].strip()}")
            else:
                final_steps.append(f"{i+1}. {step.split('.', 1)[-1].strip()}")

        return "\n".join(final_steps)

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
    print(f"Requirements Coverage: {metrics['requirements_coverage']:.1f}%\n")
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
    
    # ⭐ ADDED: Print a specific delimiter for PHP to easily find the count ⭐
    print("---TEST_CASE_COUNT_DELIMITER---")
    print(metrics['total_test_cases']) 
    # ⭐ END ADDED BLOCK ⭐

    print("\n✅ Test Case Generation Completed Successfully.")
    print(f"Output File: {output_file}")
    print(f"Total Test Cases: {metrics['total_test_cases']}")
    print(f"Requirements Coverage: {metrics['requirements_coverage']:.1f}%")
