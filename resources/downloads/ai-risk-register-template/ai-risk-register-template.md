# Ai Risk Register

_Ai Risk Register — AIPolicyTracker free template_  
_Version 1.0 · Last updated 2026-09-11_  
_Informational only, not legal advice. Completing this file does not make an organisation compliant with any law or standard. AIPolicyTracker (aipolicytracker.org), CC BY 4.0._  

## Fields

- **Risk ID** — Stable identifier (e.g. R-001).
- **System ID** — Link to the inventory row.
- **Risk domain** — One of the seven MIT AI Risk Repository domains.
- **Risk description** — What could go wrong, for whom, how.
- **Causal entity / intent / timing** — Human or AI; intentional or unintentional; pre- or post-deployment.
- **Likelihood (1-5)** — Estimated chance in the review period.
- **Impact (1-5)** — Severity for people, the organisation or society.
- **Inherent score** — Likelihood × impact before controls.
- **Existing controls** — Technical, procedural or contractual controls in place.
- **Residual score** — Score after controls.
- **Treatment** — Accept, mitigate, transfer or avoid.
- **Action and owner** — What will be done and who is accountable.
- **Due date** — When the action completes.
- **Monitoring signal** — Metric, log or test that shows the risk is under control.
- **Related obligation** — Legal or standard clause the control satisfies.
- **Last reviewed** — Date.
- **Status** — Open, in progress, closed.

## Table

| Risk ID | System ID | Risk domain | Risk description | Causal entity / intent / timing | Likelihood (1-5) | Impact (1-5) | Inherent score | Existing controls | Residual score | Treatment | Action and owner | Due date | Monitoring signal | Related obligation | Last reviewed | Status |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| R-001 | AIS-001 | 4. Malicious actors & misuse | Prompt injection via customer message causes disclosure of another customer's data | AI / Unintentional / Post-deployment | 3 | 4 | 12 | Output filter; no cross-customer retrieval; red-team test | 2 | Mitigate | Add retrieval scoping (Platform lead) | 2026-10-15 | Weekly red-team pass rate | GDPR Art. 32; EU AI Act Art. 15 | 2026-09-01 | In progress |
