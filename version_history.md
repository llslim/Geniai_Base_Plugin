# AACURA Engine Version History & Tagging Log

### Current Version: **v1.2.3**

This log documents the commit history on the `dev` branch, mapping each update to its determined semantic version based on the project's git tagging policy.

| Commit Hash | Version | Commit Message / Description | Version Type |
| :--- | :--- | :--- | :--- |
| `72d50da` | **v1.2.3** | docs: rename development branch references to dev | Revision (Patch) |
| `0aeaa66` | **v1.2.2** | docs: add scenario_test.md and link from README.md and gemini.md | Revision (Patch) |
| `70ebead` | **v1.2.1** | refactor: rename test_scenarios.php to aacuradebug_scenario.php | Revision (Patch) |
| `8bd4fd1` | **v1.2.0** | test: implement CLI options for test_scenarios.php (scenario selection, debugging toggles, config checks, phpunit) | **Minor Feature** |
| `c15500a` | **v1.1.2** | fix: change is_success() to native get_success() for core_ai response mapping | Revision (Fix) |
| `87edc9c` | **v1.1.1** | test: add PHPUnit scenarios validation test | Revision (Patch) |
| `979e64e` | **v1.1.0** | test: add CLI test script to crawl and verify all scenario conversation routes | **Minor Feature** |
| `2116d28` | **v1.0.8** | refactor: replace custom file log with native Moodle debugging() calls | Revision (Patch) |
| `cc39268` | **v1.0.7** | fix: update core_ai action namespace from action to aiactions | Revision (Fix) |
| `928f4e9` | **v1.0.6** | debug: add verbose error logging inside core_ai path | Revision (Patch) |
| `a5de90c` | **v1.0.5** | fix: pass required $DB argument to core_ai\manager() constructor (Moodle 5.x) | Revision (Fix) |
| `b4c83bc` | **v1.0.4** | debug: add core_ai process_action diagnostic logging | Revision (Patch) |
| `38161d2` | **v1.0.3** | debug: write diagnostics to readable log file | Revision (Patch) |
| `7584798` | **v1.0.2** | debug: add error_log diagnostics to generate_rubric_evaluation | Revision (Patch) |
| `f27fb2f` | **v1.0.1** | fix: resolve rubric feedback API failure on session completion | Revision (Fix) |
| *Baseline* | **v1.0.0** | Initial baseline release with Moodle 5.x component migration | **Major / Baseline** |
