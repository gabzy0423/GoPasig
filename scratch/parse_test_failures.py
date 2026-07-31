import re
import json

log_path = r"C:\Users\Acer\.gemini\antigravity-ide\brain\4d4dc69e-c458-4814-a874-352a616e23ba\.system_generated\tasks\task-793.log"

with open(log_path, 'r', encoding='utf-8', errors='ignore') as f:
    content = f.read()

# Find all FAILED lines
# Example line in log:
# FAILED  Tests\Feature\Module6MaintenanceTest > test_prior_status_restored_when_maintenance_deleted
pattern = r"FAILED\s+(Tests\\[^\s]+)\s+>\s+([^\n]+)"
matches = re.findall(pattern, content)

print(f"Total Failed Tests Found: {len(matches)}")
failures = []
for test_class, test_method in matches:
    failures.append({
        'class': test_class.strip(),
        'method': test_method.strip()
    })

print(json.dumps(failures, indent=2))
