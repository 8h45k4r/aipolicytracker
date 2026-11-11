#!/usr/bin/env python3
"""Illustrative 24-month revenue model for AIPolicyTracker.

Every input below is an ASSUMPTION unless marked MEASURED. Nothing here is a
forecast: billing has never been switched on, so there is no measured
conversion, churn or revenue to calibrate against. Change the inputs and
re-run; the report in docs/reference/growth-audit-2026-11-11.md quotes this
script's output.

    python3 scripts/revenue-model.py            # markdown tables
    python3 scripts/revenue-model.py --csv      # month-by-month CSV
"""
import sys

# MEASURED (config/billing.php, pricing page): list prices.
BUILDER_MONTHLY = 49.0
BUILDER_YEARLY = 490.0
TEAM_MONTHLY = 199.0          # display price; sold by written agreement
COMMERCIAL_FROM = 5000.0      # "From $5,000 per year"; sold by written agreement

SCENARIOS = {
    # signups: new verified accounts per month at month 1; growth: monthly growth of that number.
    # conv: share of new accounts that start Builder within their first month.
    # churn: monthly cancellation rate of Builder.
    # annual: share of Builder buyers choosing the yearly price.
    # team_every: months between Team contracts (0 = none); commercial_every likewise.
    # acv: Commercial annual contract value. reviewer: paid verification per month.
    # content: cash spent on content and outreach per month (counts as acquisition cost).
    'conservative': dict(signups=40, growth=0.03, conv=0.015, churn=0.06, annual=0.2, team_every=0, team_start=12, commercial_every=12, commercial_start=9, acv=5000, reviewer=0, content=0, win_rate=0.2),
    'base': dict(signups=100, growth=0.05, conv=0.03, churn=0.04, annual=0.3, team_every=3, team_start=4, commercial_every=3, commercial_start=6, acv=5000, reviewer=1000, content=500, win_rate=0.25),
    'upside': dict(signups=250, growth=0.07, conv=0.04, churn=0.03, annual=0.35, team_every=2, team_start=3, commercial_every=2, commercial_start=4, acv=6000, reviewer=2500, content=1500, win_rate=0.3),
}

# ASSUMPTIONS on cost, the same in every scenario.
INFRA = 100.0           # hosting, backups, monitoring a month (host is shared today; not measured)
EMAIL = 20.0            # transactional email a month
PAY_FEE_PCT = 0.05      # payment provider (merchant of record) fee on card revenue: check the provider's rate
PAY_FEE_FIXED = 0.50    # per card transaction
SUPPORT_H_PER_10 = 2.0  # support hours a month per 10 paying Builder accounts
SUPPORT_RATE = 50.0     # value of an hour of support time
ONBOARD_H = 6.0         # onboarding hours per Team or Commercial contract
TEAM_CHURN_Y = 0.15     # annual non-renewal of Team and Commercial contracts
COMMERCIAL_QUALIFIED_PER_DEAL = None  # derived: deals / win_rate = qualified enquiries needed


def run(p):
    builder_m = builder_y = 0.0
    team = commercial = 0.0
    rows = []
    cum_rev = cum_cost = 0.0
    new_paid_total = 0.0
    for m in range(1, 25):
        signups = p['signups'] * (1 + p['growth']) ** (m - 1)
        new_paid = signups * p['conv']
        new_paid_total += new_paid
        builder_m = builder_m * (1 - p['churn']) + new_paid * (1 - p['annual'])
        builder_y = builder_y * (1 - p['churn']) + new_paid * p['annual']
        if p['team_every'] and m >= p['team_start'] and (m - p['team_start']) % p['team_every'] == 0:
            team += 1
        if p['commercial_every'] and m >= p['commercial_start'] and (m - p['commercial_start']) % p['commercial_every'] == 0:
            commercial += 1
        team *= (1 - TEAM_CHURN_Y) ** (1 / 12)
        commercial *= (1 - TEAM_CHURN_Y) ** (1 / 12)
        builder_rev = builder_m * BUILDER_MONTHLY + builder_y * BUILDER_YEARLY / 12
        team_rev = team * TEAM_MONTHLY
        comm_rev = commercial * p['acv'] / 12
        mrr = builder_rev + team_rev + comm_rev
        builder = builder_m + builder_y
        fees = builder_rev * PAY_FEE_PCT + builder_m * PAY_FEE_FIXED + builder_y * PAY_FEE_FIXED / 12
        support = builder / 10 * SUPPORT_H_PER_10 * SUPPORT_RATE
        onboarding = ((1 if p['team_every'] and m >= p['team_start'] and (m - p['team_start']) % p['team_every'] == 0 else 0)
                      + (1 if p['commercial_every'] and m >= p['commercial_start'] and (m - p['commercial_start']) % p['commercial_every'] == 0 else 0)) * ONBOARD_H * SUPPORT_RATE
        cogs = INFRA + EMAIL + fees + support + onboarding
        opex = p['reviewer'] + p['content']
        cum_rev += mrr
        cum_cost += cogs + opex
        rows.append(dict(month=m, signups=signups, builder=builder, team=team, commercial=commercial, builder_rev=builder_rev, team_rev=team_rev, comm_rev=comm_rev, mrr=mrr, cogs=cogs, opex=opex, cum_rev=cum_rev, cum_cost=cum_cost, new_paid=new_paid))
    return rows, new_paid_total


def summary(name, p):
    rows, new_paid_total = run(p)
    out = {}
    for m in (12, 24):
        r = rows[m - 1]
        arpa_builder = r['builder_rev'] / r['builder'] if r['builder'] else 0
        gm = (r['mrr'] - r['cogs']) / r['mrr'] if r['mrr'] else 0
        ltv = arpa_builder * gm / p['churn'] if p['churn'] else 0
        paid_in_period = sum(x['new_paid'] for x in rows[:m])
        cac = (p['content'] * m) / paid_in_period if paid_in_period else 0
        payback = cac / (arpa_builder * gm) if arpa_builder * gm > 0 else 0
        fixed = INFRA + EMAIL + p['reviewer'] + p['content']
        variable_share = PAY_FEE_PCT + (SUPPORT_H_PER_10 * SUPPORT_RATE / 10) / arpa_builder if arpa_builder else 1
        breakeven = fixed / (arpa_builder * (1 - variable_share)) if arpa_builder and variable_share < 1 else float('inf')
        commercial_pipeline = r['commercial'] * p['acv']
        qualified_needed = (12 / p['commercial_every'] if p['commercial_every'] else 0) / p['win_rate'] if p['win_rate'] else 0
        out[m] = dict(mrr=r['mrr'], arr=r['mrr'] * 12, builder=r['builder'], team=r['team'], commercial=r['commercial'], builder_rev=r['builder_rev'], team_rev=r['team_rev'], comm_rev=r['comm_rev'],
                      conv=p['conv'], arpa=arpa_builder, churn_m=p['churn'], churn_y=1 - (1 - p['churn']) ** 12, ltv=ltv, gm=gm, cac=cac, payback=payback,
                      cogs=r['cogs'], opex=r['opex'], net=r['mrr'] - r['cogs'] - r['opex'], cum_rev=r['cum_rev'], cum_cost=r['cum_cost'], breakeven=breakeven,
                      commercial_arr=commercial_pipeline, qualified_needed=qualified_needed)
    return out


def money(x):
    return f"${x:,.0f}"


if __name__ == '__main__':
    if '--csv' in sys.argv:
        print('scenario,month,signups,builder,team,commercial,mrr,cogs,opex')
        for name, p in SCENARIOS.items():
            for r in run(p)[0]:
                print(f"{name},{r['month']},{r['signups']:.0f},{r['builder']:.1f},{r['team']:.2f},{r['commercial']:.2f},{r['mrr']:.0f},{r['cogs']:.0f},{r['opex']:.0f}")
        sys.exit()
    results = {n: summary(n, p) for n, p in SCENARIOS.items()}
    lines = [('MRR', lambda s: money(s['mrr'])), ('ARR (MRR × 12)', lambda s: money(s['arr'])),
             ('Builder accounts', lambda s: f"{s['builder']:.0f}"), ('Team contracts', lambda s: f"{s['team']:.1f}"), ('Commercial contracts', lambda s: f"{s['commercial']:.1f}"),
             ('Builder MRR', lambda s: money(s['builder_rev'])), ('Team MRR', lambda s: money(s['team_rev'])), ('Commercial MRR', lambda s: money(s['comm_rev'])),
             ('Free-to-paid conversion (assumed)', lambda s: f"{s['conv']:.1%}"), ('Builder ARPA a month', lambda s: money(s['arpa'])),
             ('Builder churn a month / a year', lambda s: f"{s['churn_m']:.0%} / {s['churn_y']:.0%}"), ('Builder LTV (ARPA × margin ÷ churn)', lambda s: money(s['ltv'])),
             ('Gross margin', lambda s: f"{s['gm']:.0%}"), ('Cash CAC (content spend ÷ new paying)', lambda s: money(s['cac'])), ('CAC payback (months)', lambda s: f"{s['payback']:.1f}"),
             ('Cost of revenue a month', lambda s: money(s['cogs'])), ('Reviewer and content a month', lambda s: money(s['opex'])), ('Net a month', lambda s: money(s['net'])),
             ('Cumulative revenue', lambda s: money(s['cum_rev'])), ('Cumulative cost', lambda s: money(s['cum_cost'])),
             ('Break-even Builder accounts', lambda s: f"{s['breakeven']:.0f}"), ('Commercial ARR in force', lambda s: money(s['commercial_arr'])),
             ('Qualified Commercial enquiries needed a year', lambda s: f"{s['qualified_needed']:.0f}")]
    for m in (12, 24):
        print(f"\n**Month {m}**\n")
        print('| Metric | ' + ' | '.join(n.capitalize() for n in results) + ' |')
        print('|---|' + '---|' * len(results))
        for label, fn in lines:
            print(f"| {label} | " + ' | '.join(fn(results[n][m]) for n in results) + ' |')
