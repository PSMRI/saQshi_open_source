from reportlab.lib.pagesizes import A4
from reportlab.pdfgen import canvas
from reportlab.lib.colors import HexColor, white
from reportlab.lib.utils import ImageReader
from reportlab.pdfbase.pdfmetrics import stringWidth
from pathlib import Path

ROOT=Path.cwd(); OUT=ROOT/'output'/'pdf'/'Saqshi_Platform_Brief.pdf'
W,H=A4
NAVY=HexColor('#102B43'); GREEN=HexColor('#2E786F'); ORANGE=HexColor('#F68B25'); BLUE=HexColor('#3478F6'); PALE=HexColor('#F4F7FA'); INK=HexColor('#193B5B'); MUTED=HexColor('#5E7488'); LINE=HexColor('#D7E1EA'); MINT=HexColor('#E8F5F0'); LAV=HexColor('#F1EDFF'); ROSE=HexColor('#FDECEF')
logo=ROOT/'ui'/'assets'/'images'/'logo1.png'; photo=ROOT/'ui'/'assets'/'images'/'landing-facility-team-digital.png'
c=canvas.Canvas(str(OUT),pagesize=A4); c.setTitle('SaQshi Platform Brief')
def text(x,y,s,size=10,color=INK,font='Helvetica',maxw=None,leading=None):
    c.setFont(font,size); c.setFillColor(color); leading=leading or size*1.35
    words=s.split(); line=''; rows=[]
    for word in words:
        probe=(line+' '+word).strip()
        if maxw and stringWidth(probe,font,size)>maxw and line: rows.append(line); line=word
        else: line=probe
    if line: rows.append(line)
    for r in rows: c.drawString(x,y,r); y-=leading
    return y
def rr(x,y,w,h,fill,r=10): c.setFillColor(fill);c.roundRect(x,y,w,h,r,stroke=0,fill=1)
def header(kicker,title,sub,p):
    c.setFillColor(PALE);c.rect(0,0,W,H,stroke=0,fill=1);rr(42,H-56,160,22,GREEN,8);c.setFillColor(white);c.setFont('Helvetica-Bold',7);c.drawCentredString(122,H-49,kicker)
    c.setFillColor(INK);c.setFont('Helvetica-Bold',24);c.drawString(42,H-92,title);c.setFillColor(ORANGE);c.rect(42,H-105,48,4,stroke=0,fill=1);text(42,H-128,sub,10,MUTED,maxw=510)
    c.drawImage(str(logo),W-105,H-55,62,22,mask='auto',preserveAspectRatio=True,anchor='c')
    c.setFillColor(NAVY);c.rect(0,0,W,25,stroke=0,fill=1);text(42,9,'SaQshi | Platform brief',7,white);text(W-62,9,f'0{p}',7,white)
def card(x,y,w,h,ttl,body,fill=white,n=None):
    rr(x,y,w,h,fill); 
    if n:
        c.setFillColor(ORANGE if n==1 else BLUE if n==2 else GREEN);c.circle(x+25,y+h-28,14,stroke=0,fill=1);c.setFillColor(white);c.setFont('Helvetica-Bold',10);c.drawCentredString(x+25,y+h-32,str(n))
    tx=x+20 if not n else x+50; c.setFillColor(INK);c.setFont('Helvetica-Bold',12);c.drawString(tx,y+h-31,ttl);text(x+20,y+h-58,body,9,MUTED,maxw=w-40,leading=12)

# Page 1
c.setFillColor(NAVY);c.rect(0,0,W,H,stroke=0,fill=1)
c.drawImage(str(photo),W-245,0,245,H,mask='auto',preserveAspectRatio=False)
c.setFillColor(NAVY);c.setFillAlpha(.84);c.rect(0,0,385,H,stroke=0,fill=1);c.setFillAlpha(1)
c.drawImage(str(logo),42,H-75,150,45,mask='auto',preserveAspectRatio=True,anchor='c')
c.setFillColor(white);c.setFont('Helvetica-Bold',34);c.drawString(42,H-205,'SaQshi')
text(42,H-250,'A digital platform for facility quality assessment, continuous improvement and programme monitoring',18,white,'Helvetica',maxw=310,leading=25)
rr(42,245,284,84,HexColor('#1C4867'));c.setFillColor(white);c.setFont('Helvetica-Bold',12);c.drawString(60,302,'What SaQshi brings together')
text(60,280,'Assessment, CQI action planning, performance monitoring, certification and reporting in one connected workflow.',10,HexColor('#DCE8F5'),maxw=245,leading=14)
text(42,104,'Based on SaQshi Help Documentation and GitBook project documentation',9,HexColor('#DCE8F5'));text(42,80,'Prepared for client discussion | Public health facilities',9,HexColor('#DCE8F5'))
c.showPage()

# Page 2
header('HOW SAQSHI WORKS','Assessment to improvement action','The platform maintains one facility-level record across assessment, CQI, performance, certification and reporting.',2)
steps=[('1','Create or continue assessment'),('2','Activate applicable departments'),('3','Capture checklist responses'),('4','Review gaps and set action plans'),('5','Add evidence and close gaps'),('6','Use reports and monitoring')]
c.setStrokeColor(LINE);c.setLineWidth(2);c.line(60,565,535,565)
for i,(n,label) in enumerate(steps):
    x=47+i*82;c.setFillColor(ORANGE if i==0 else BLUE);c.circle(x+13,565,16,stroke=0,fill=1);c.setFillColor(white);c.setFont('Helvetica-Bold',10);c.drawCentredString(x+13,561,n);text(x-8,526,label,8,INK,'Helvetica-Bold',maxw=48,leading=10)
card(42,305,160,154,'Assessment workflow','Framework and department setup, assessor information and checkpoint scoring keep the assessment organised.',MINT,1)
card(218,305,160,154,'CQI follow-through','Non-compliance and partial-compliance checkpoints become action plans with owner, date, evidence and closure.',HexColor('#EAF2FF'),2)
card(394,305,160,154,'Monitoring and reports','Role-scoped dashboards, risk analysis, progress reports, KPI/outcome trends and certification tracking support follow-up.',LAV,3)
rr(42,184,512,78,white);c.setFillColor(INK);c.setFont('Helvetica-Bold',12);c.drawString(63,235,'Scoring creates a practical next step')
text(63,214,'In the NQAS profile, scores are 0 for non-compliance, 1 for partial compliance and 2 for full compliance. Scores of 0 or 1 feed the gap analysis and CQI action-plan workflow.',9,MUTED,maxw=465,leading=13)
text(42,63,'Source: SaQshi User Guide and Project Overview / NQAS Alignment (GitBook docs).',7,MUTED)
c.showPage()

# Page 3
header('CLIENT VALUE','Role-based programme oversight','SaQshi separates facility work from supervisory monitoring while retaining an accountable audit trail.',3)
card(42,505,245,130,'Facility teams','Create assessments, complete checklists, manage actions, attach evidence and enter monthly KPI or outcome data.',MINT)
card(307,505,245,130,'Assessor and monitoring roles','Assessors work on mapped facilities; block, district, division and state users see only their assigned scope.',HexColor('#EAF2FF'))
card(42,347,245,130,'Governance boundary','SaQshi supports facility-level quality information. It does not require patient-level clinical records or identifiers.',LAV)
card(307,347,245,130,'Configurable foundation','Frameworks, masters, performance indicators, certification rules and geography settings use configuration files.',ROSE)
rr(42,183,512,114,NAVY);c.setFillColor(white);c.setFont('Helvetica-Bold',14);c.drawString(63,265,'A practical first pilot')
for i,s in enumerate(['Agree the facility cohort, quality framework and reporting needs.','Configure roles, masters, workflow rules and dashboards.','Pilot with facility and monitoring users, then refine before scale.']):
    y=240-i*22;c.setFillColor(ORANGE);c.circle(69,y+3,5,stroke=0,fill=1);text(83,y,s,9,HexColor('#DCE8F5'),maxw=440)
text(42,119,'Documentation consulted',9,INK,'Helvetica-Bold');text(42,101,'SaQshi User Documentation (ui/help/documentation.html); SaQshi User Guide; Project Overview and NQAS Alignment; Technical Architecture.',7,MUTED,maxw=510,leading=10)
text(42,63,'Contact: tech4gov@piramalswasthya.org | The rollout outline is a proposed discussion approach.',7,MUTED)
c.save()
