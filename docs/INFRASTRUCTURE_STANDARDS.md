# VirtueNet Infrastructure Standard

## Source of truth

The `github_deployed_repos` and `vps_nodes` tables are the platform inventory. Coolify remains the deployment system and is connected with a read-only API token. A production application is cutover-ready only when its department, VPS cluster, Coolify UUID, Git repository, domain, `main` environment, owner, and cost center are complete in Applications.

## Domain and cluster naming

- Standard production domain: `{app}.{department-code}.virtuenet.space` in lowercase kebab-case.
- Standard cluster key: `{department-code}-{purpose}`, for example `it-primary`.
- Shadow deployments use the `virtuenet` environment; production uses `main`.
- A non-standard domain must include a registered exception reason in Applications.
- Domains are unique in PostgreSQL. Renaming or moving a service must update the existing inventory row; never create a second active mapping.

## Department cluster path

Example target path: Coolify server UUID → `it-primary` VPS node → Information Technology department → `sample.it.virtuenet.space`. Record the real Coolify server UUID and application UUID through Gateway connections and Applications; do not put credentials or screenshots containing secrets in the repository.

Evidence required before production cutover:

1. Coolify inventory sync reports the expected application UUID, `main` branch, and runtime state.
2. Applications shows department, cluster, owner, and cost center without a governance warning.
3. `https://{domain}/health` is online for two consecutive five-minute probes.
4. DNS resolves to the destination cluster and the old workload is still available for rollback.

## Workload migration playbook

1. Register the destination VPS node and verify its capacity snapshot.
2. Deploy the same commit to the destination as `virtuenet`; keep the source workload running.
3. Synchronize Coolify inventory and complete governance fields.
4. Validate readiness, database connectivity, queue processing, and application smoke tests on the shadow endpoint.
5. Lower DNS TTL, switch the domain once, and monitor independent health probes.
6. Keep the source workload intact through the rollback window. Roll back DNS if health degrades.
7. After parity and the rollback window are proven, set environment to `main` and retire the old workload.
