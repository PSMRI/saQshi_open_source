import csv
import json
from collections import OrderedDict
from pathlib import Path

SOURCE = Path(r"C:\Users\manish_k\Desktop\jh\chk_list2.csv")
TARGET = Path("api/config/frameworks/saqshi-nqas.generated.json")
CURRENT = Path("api/config/frameworks/saqshi-nqas.json")

FACILITY_IDS = {
    "CHC": 1, "DH": 2, "PHC": 3, "UPHC": 5, "AAM-SC": 8,
    "APHC (PHC without bed)": 9, "SDH": 10,
}
DEFAULT_RESPONSE = {
    "type": "radio", "mandatory": True, "remarks_required": False,
    "evidence_required": False,
    "options": [
        {"label": "Fully Compliant", "value": 2, "score": 2},
        {"label": "Partially Compliant", "value": 1, "score": 1},
        {"label": "Non Compliant", "value": 0, "score": 0},
    ],
}

def integer(value):
    value = (value or "").strip()
    return int(value) if value else None

def text(value):
    return (value or "").strip()

def existing_responses():
    current = json.loads(CURRENT.read_text(encoding="utf-8"))
    responses = {}
    for facility in current:
        for department in facility.get("departments", []):
            for concern in department.get("concerns", []):
                for subtype in concern.get("subtypes", []):
                    for checkpoint in subtype.get("checkpoints", []):
                        responses[str(checkpoint["csqa_id"])] = checkpoint.get("response", DEFAULT_RESPONSE)
    return responses

def repair_row(row):
    # Three source rows omit fac_type_id but contain a known facility type at that position.
    if text(row[10]) in FACILITY_IDS:
        facility_name = text(row[10])
        row = row[:10] + [str(FACILITY_IDS[facility_name])] + row[10:19]
        row[18] = row[18] or "NQAS"
    if len(row) != 20 or text(row[10]) not in {str(v) for v in FACILITY_IDS.values()}:
        raise ValueError(f"Invalid source row for CSQA {row[0] if row else '?'}")
    return row

responses = existing_responses()
facilities = OrderedDict()
checkpoint_ids = set()
repaired_ids = []

with SOURCE.open(encoding="latin-1", newline="") as file:
    reader = csv.reader(file)
    headers = next(reader)
    if headers[:19] != [
        "csqa_id", "concern_id", "concern_name", "concern_des", "c_subtype_id",
        "area_of_con_subtypedeatils", "Reference_No", "c_subtype_Reference_No_fk",
        "csqa_reference_id", "Means_of_Verification", "fac_type_id", "facilities_type",
        "Measurable_Element", "Checkpoint", "Assessment_Method", "action_plan",
        "fac_dept_id", "dept_name", "program_tag",
    ]:
        raise ValueError("Unexpected chk_list2.csv column order")
    for raw in reader:
        if not raw:
            continue
        missing_type_id = text(raw[10]) in FACILITY_IDS
        row = repair_row(raw)
        if missing_type_id:
            repaired_ids.append(text(row[0]))
        (
            csqa_id, concern_id, concern_name, concern_des, subtype_id, subtype_details,
            reference_no, subtype_reference_no, csqa_reference_id, means_of_verification,
            facility_id, facility_name, measurable_element, checkpoint, assessment_method,
            action_plan, department_id, department_name, program_tag, _
        ) = row
        if csqa_id in checkpoint_ids:
            raise ValueError(f"Duplicate CSQA ID: {csqa_id}")
        checkpoint_ids.add(csqa_id)
        facility = facilities.setdefault(integer(facility_id), {
            "fac_type_id": integer(facility_id), "facilities_type": text(facility_name),
            "departments": OrderedDict(),
        })
        department = facility["departments"].setdefault(integer(department_id), {
            "fac_dept_id": integer(department_id), "dept_name": text(department_name),
            "program_tag": text(program_tag) or "NQAS", "concerns": OrderedDict(),
        })
        concern = department["concerns"].setdefault(integer(concern_id), {
            "concern_id": integer(concern_id), "concern_name": text(concern_name),
            "concern_des": text(concern_des), "subtypes": OrderedDict(),
        })
        subtype = concern["subtypes"].setdefault(integer(subtype_id), {
            "c_subtype_id": integer(subtype_id),
            "area_of_con_subtypedeatils": text(subtype_details),
            "Reference_No": text(reference_no), "checkpoints": [],
        })
        subtype["checkpoints"].append({
            "csqa_id": integer(csqa_id),
            "c_subtype_Reference_No_fk": text(subtype_reference_no),
            "csqa_reference_id": text(csqa_reference_id),
            "Means_of_Verification": text(means_of_verification),
            "Measurable_Element": text(measurable_element), "Checkpoint": text(checkpoint),
            "Assessment_Method": text(assessment_method), "action_plan": text(action_plan),
            "program_tag": text(program_tag) or "NQAS",
            "response": responses.get(csqa_id, DEFAULT_RESPONSE),
        })

output = []
for facility in facilities.values():
    departments = []
    for department in facility["departments"].values():
        concerns = []
        for concern in department["concerns"].values():
            concern["subtypes"] = list(concern["subtypes"].values())
            concerns.append(concern)
        department["concerns"] = concerns
        departments.append(department)
    facility["departments"] = departments
    output.append(facility)

if len(checkpoint_ids) != 26223:
    raise ValueError(f"Expected 26223 checkpoints, generated {len(checkpoint_ids)}")
TARGET.write_text(json.dumps(output, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
print(json.dumps({
    "checkpoints": len(checkpoint_ids),
    "facility_types": {str(f["fac_type_id"]): sum(len(s["checkpoints"]) for d in f["departments"] for c in d["concerns"] for s in c["subtypes"]) for f in output},
    "repaired_source_rows": repaired_ids,
    "output": str(TARGET),
}))
