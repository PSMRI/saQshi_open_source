# State Admin, DPO and Assessor: One-Page Assessment Guide

Version: 1.2
Updated: 2026-08-26

This guide explains who does what, how a School/Facility is assigned, and how
an assessment is completed in SaQshi.

## Start and sign in safely

1. Open the organisation-approved address: `{main_url}/`.
2. Select **Login**, or open `{main_url}/ui/login.html`.
3. Enter the assigned Assessor Code/username, password and captcha.
4. Change a temporary password when prompted.

The start page is selected by the deployment profile: education deployments use school terminology and healthcare deployments use facility terminology. Do not enter credentials on a copied link or unfamiliar page, and never share passwords, captcha answers, session data or temporary passwords through an unapproved channel.

## Roles and scope

| Role | Scope in SaQshi |
|---|---|
| State Admin | Creates DPO/Assessor accounts, maps Schools/Facilities to one or more assessors, assigns classes, views all state reports and manages transfers. |
| DPO / Assessor | Can view and assess only the Schools/Facilities and classes assigned by State Admin. Can complete checklists and download own assessment reports. |
| School / Facility User | Uses the normal facility workflow and sees only their own School/Facility data. |

## Profile terms: Education and Healthcare

Use the terms and menu labels shown by your deployment. The same assignment, checklist, reassessment and reporting controls apply; only the programme terminology and configured assessment structure differ.

| Education profile | Healthcare profile |
|---|---|
| School | Facility |
| UDISE Code | NIN |
| DPO / Assessor | Assessor |
| Class (where configured) | Department / service area (where configured) |
| School assessment | Facility quality assessment |

Education users should use the **School**, **UDISE Code**, **DPO / Assessor Dashboard** and **Class** labels shown in their deployment. Healthcare users should use the **Facility**, **NIN**, **Assessor Dashboard** and **Department/service-area** labels shown in theirs.

## 1. State Admin: create a DPO / Assessor

1. Login with the State Admin account.
2. Open **State Monitoring → DPO / Assessor Management**.
3. Enter Assessor Code, Name, Designation, Mobile and Email.
4. Leave **Linked User ID** blank to let SaQshi create the login automatically.
5. Click **Save**. The Assessor Code becomes the login username; copy the
   temporary password shown on screen and share it securely.
6. Select the DPO/Assessor from the list.

## 2. State Admin: map one or more assessors to a School / Facility

1. With the DPO/Assessor selected, search by School/Facility name, UDISE/NIN,
   district or block.
2. Select one or more results and click **Assign selected**.
3. The assigned unit appears in the mapping list with start date and status.

To assign multiple assessors to the same School/Facility:

1. Select the first DPO/Assessor and assign the School/Facility.
2. Select each additional DPO/Assessor and assign the same School/Facility.
3. For shared work, assign a different Class (Education) or Department/service area (Healthcare) to each assessor. The same configured work area can be claimed and assessed by only one assessor at a time.

When another Mentor/Assessor has a class in progress, SaQshi shows that the
class is in progress by another Mentor and does not offer **Claim & Activate**.
The other assessor can claim any available class instead.

To transfer the unit later:

1. Select the current DPO/Assessor.
2. Click **End Assignment** beside the School/Facility and confirm.
3. Select the next DPO/Assessor and assign the same unit.

End the prior assignment only when transferring all remaining work from one
assessor to another. SaQshi retains old assessments and assignments as history.

## 3. DPO / Assessor: perform the checklist

1. Login using the assigned account. Change the temporary password if asked.
2. Open **DPO / Assessor Dashboard**.
3. Review Assigned Schools/Facilities, Total Assessments, Completed,
   In Progress and Not Started counts.
4. For a new Class (Education) or Department/service area (Healthcare), click **Start Assessment**. For a previously completed work area, click **Start Reassessment**. A different configured work area is always a new assessment, not a reassessment.
5. If required, claim the assigned Class (Education) or Department/service area (Healthcare) before starting the checklist.
6. Save DPO/Assessor and Assessee details.
7. Open **Checklist**, select the applicable Class/Department, Area of Concern and Standard,
   then answer every checkpoint. Save each response and supporting evidence if
   required.
8. SaQshi automatically marks the assigned Class/Department complete when all of its checkpoints are answered. When every active work area is complete,
   the assessment is marked **Completed**.

Only record assessment observations and approved improvement evidence. Do not enter patient/student personal data, passwords, credentials, raw error messages or evidence containing personal information. Redact the evidence or obtain deployment-owner approval before upload if it contains any personal information.

If the assessor has already claimed an in-progress Class/Department, **Continue Assessment** opens that checklist directly. Class/Department Activation is shown only when no work area has been claimed yet.

## Shared rounds and overall score

- All first-time class/department assessments for the same School/Facility
  belong to Round 1, even when different assessors start on different dates.
- A reassessment of a completed class/department belongs to the next round;
  rounds may progress in parallel while earlier rounds still have pending work.
- Overall score is weighted by completed checkpoint totals. The report shows
  completed classes/departments out of the configured total, round status and
  the completion date when the round is complete.
- In Domain View, SaQshi first shows only Domain tabs. Select a Domain tab to
  load its checklist; completed Domains are marked green.

## 4. Reassessment and reports

- A completed assessment changes the dashboard action to **Start Reassessment**.
  SaQshi automatically creates the new assessment name and dates.
- To stop an unfinished assessment, use **Cancel** from the DPO/Assessor
  dashboard. Saved responses remain in history.
- Open **Reports** to view assessment details, score and checkpoint totals.
  In **Assessment Score Trend**, search by School/Facility name or UDISE/NIN to
  compare assessment rounds.

## If something does not work

- **No assigned School/Facility:** contact State Admin to confirm the account, assignment status and Class/Department allocation.
- **Class/Department already in progress:** choose another assigned available work area, or ask State Admin to confirm the intended assessor.
- **Unauthorized or session expired:** sign in again; do not attempt to use another user's account.
- **Save or network error:** note the page name, approximate time and visible message; report these to support without sending screenshots that contain personal data.

## Quick flow

```text
State Admin creates DPO/Assessor
        → assigns School/Facility
        → DPO/Assessor starts assessment
        → claims assigned class
        → fills assessor information
        → completes checklist
        → SaQshi marks assessment completed
        → State Admin or DPO/Assessor starts reassessment when due
```
