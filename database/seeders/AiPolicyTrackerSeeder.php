<?php

namespace Database\Seeders;

use Carbon\Carbon;
use App\Models\News;
use App\Models\Status;
use App\Models\Country;
use Illuminate\Support\Str;
use App\Models\AiPolicyTracker;
use Illuminate\Database\Seeder;
use App\Models\AIPolicyActivityLog;
use App\Helpers\Logs\AiPolicyActivityLogHelper;

class AiPolicyTrackerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Array of sample policies
        $policies = [
            [
                'ai_policy_name' => 'Act of AI Regulation',
                'governing_body' => 'Federal Trade Commission (FTC)',
                'announcement_year' => '2023-05-01',
                'whitepaper_document_link' => 'http://www.ftc.gov/ai-regulation',
                'technology_partners' => 'TechSafe Inc.',
                'governance_structure' => 'Federal AI Oversight Board',
                'main_motivation' => 'To enhance transparency and accountability in AI systems',
                'description' => 'The Act of AI Regulation aims to set standards for AI transparency, requiring companies to disclose the use of AI systems and ensure they adhere to ethical guidelines.',
                'gov_ai_index' => 'policy'
            ],
            [
                'ai_policy_name' => 'AI Ethics Act',
                'governing_body' => 'European Commission',
                'announcement_year' => '2024-05-01',
                'whitepaper_document_link' => 'http://www.europa.eu/ai-ethics',
                'technology_partners' => 'EuroTech Solutions',
                'governance_structure' => 'EU AI Ethics Committee',
                'main_motivation' => 'To ensure AI technologies respect fundamental rights and freedoms',
                'description' => 'This policy aims to establish ethical guidelines for the development and deployment of AI technologies across the EU to protect citizens\' rights and prevent misuse.',
                'gov_ai_index' => 'policy'
            ],
            [
                'ai_policy_name' => 'AI Surveillance Regulation',
                'governing_body' => 'State Council of China',
                'announcement_year' => '2022-05-01',
                'whitepaper_document_link' => 'http://www.china.gov.cn/ai-surveillance',
                'technology_partners' => 'ChinaTech Ltd.',
                'governance_structure' => 'National AI Surveillance Authority',
                'main_motivation' => 'To regulate the use of AI in surveillance and public security',
                'description' => 'This regulation focuses on the use of AI technologies for surveillance purposes, aiming to balance public security with individual privacy concerns.',
                'gov_ai_index' => 'strategy',
                // 'gov_ai_index_name'=>'strategy 1',
            ],
            [
                'ai_policy_name' => 'AI Innovation Act',
                'governing_body' => 'Ministry of Science and ICT',
                'announcement_year' => '1990-05-01',
                'whitepaper_document_link' => 'http://www.msit.go.kr/ai-innovation',
                'technology_partners' => 'K-Next Technologies',
                'governance_structure' => 'Korean AI Innovation Council',
                'main_motivation' => 'To foster innovation in AI technologies and support startups',
                'description' => 'The AI Innovation Act is designed to encourage the growth of AI technology startups by providing funding and support while ensuring innovation aligns with national interests.',
                'gov_ai_index' => 'strategy',
                // 'gov_ai_index_name'=>'strategy 2',
            ],
            [
                'ai_policy_name' => 'AI Accountability Framework',
                'governing_body' => 'Ministry of Electronics and IT',
                'announcement_year' => '1995-05-01',
                'whitepaper_document_link' => 'http://www.meit.gov.in/ai-accountability',
                'technology_partners' => 'IndoTech Innovations',
                'governance_structure' => 'AI Accountability Task Force',
                'main_motivation' => 'To ensure accountability and traceability in AI systems',
                'description' => 'This framework outlines requirements for AI systems to be accountable and traceable, focusing on ensuring that AI decisions are transparent and can be audited.',
            ],
            [
                'ai_policy_name' => 'AI Fairness Act',
                'governing_body' => 'Australian Government',
                'announcement_year' => '1992-05-01',
                'whitepaper_document_link' => 'http://www.australia.gov.au/ai-fairness',
                'technology_partners' => 'AussieAI Solutions',
                'governance_structure' => 'Australian AI Fairness Board',
                'main_motivation' => 'To promote fairness and equity in AI systems',
                'description' => 'The AI Fairness Act is aimed at ensuring that AI systems are developed and used in a manner that is fair and equitable, minimizing biases and discrimination.',
            ],
            [
                'ai_policy_name' => 'AI Safety Protocol',
                'governing_body' => 'Japanese Ministry of Internal Affairs and Communications',
                'announcement_year' => '2010-05-01',
                'whitepaper_document_link' => 'http://www.safety.japan.go.jp/ai-protocol',
                'technology_partners' => 'JapanTech Corp.',
                'governance_structure' => 'National AI Safety Council',
                'main_motivation' => 'To ensure the safe deployment of AI technologies',
                'description' => 'The AI Safety Protocol focuses on ensuring that AI technologies are deployed safely, minimizing risks and addressing potential hazards associated with their use.',
            ],
            [
                'ai_policy_name' => 'AI Transparency Act',
                'governing_body' => 'Canadian Government',
                'announcement_year' => '2013-05-01',
                'whitepaper_document_link' => 'http://www.canada.ca/ai-transparency',
                'technology_partners' => 'CanadaAI Innovations',
                'governance_structure' => 'Canadian AI Transparency Authority',
                'main_motivation' => 'To increase transparency in AI decision-making',
                'description' => 'This act is aimed at increasing transparency in AI decision-making processes, requiring organizations to provide clear explanations of AI-driven decisions.',
            ],
            [
                'ai_policy_name' => 'AI Ethics and Governance Framework',
                'governing_body' => 'Indian Ministry of IT',
                'announcement_year' => '2016-05-01',
                'whitepaper_document_link' => 'http://www.india.gov.in/ai-ethics',
                'technology_partners' => 'IndiaTech Solutions',
                'governance_structure' => 'Indian AI Ethics Committee',
                'main_motivation' => 'To establish ethical standards and governance in AI technologies',
                'description' => 'The AI Ethics and Governance Framework establishes ethical standards and governance structures for AI technologies to ensure they are used responsibly and in alignment with societal values.',
            ],
            [
                'ai_policy_name' => 'AI Impact Assessment Act',
                'governing_body' => 'Brazilian Ministry of Science and Technology',
                'announcement_year' => '2024-05-01',
                'whitepaper_document_link' => 'http://www.brazil.gov.br/ai-impact-assessment',
                'technology_partners' => 'BrazilAI Corp.',
                'governance_structure' => 'Brazilian AI Impact Assessment Board',
                'main_motivation' => 'To assess and mitigate the impact of AI technologies',
                'description' => 'The AI Impact Assessment Act focuses on assessing and mitigating the impact of AI technologies, ensuring they do not adversely affect society or the environment.',
            ],
            [
                'ai_policy_name' => 'Global AI Collaboration Framework',
                'governing_body' => 'United Nations',
                'announcement_year' => '2023-05-01',
                'whitepaper_document_link' => 'http://www.un.org/ai-collaboration',
                'technology_partners' => 'GlobalTech Alliance',
                'governance_structure' => 'Global AI Collaboration Council',
                'main_motivation' => 'To foster international collaboration on AI development',
                'description' => 'This framework aims to promote international collaboration on AI research and development, ensuring that global standards are aligned and shared.',
            ],
            [
                'ai_policy_name' => 'AI Research and Development Act',
                'governing_body' => 'US National Science Foundation',
                'announcement_year' => '2020-05-01',
                'whitepaper_document_link' => 'http://www.nsf.gov/ai-research',
                'technology_partners' => 'ResearchTech Labs',
                'governance_structure' => 'AI Research Advisory Board',
                'main_motivation' => 'To advance AI research and support innovation',
                'description' => 'The AI Research and Development Act provides funding and support for cutting-edge AI research, aiming to advance the field and drive innovation.',
            ],
        ];

        // Real news data related to each policy
        $statusId = Status::inRandomOrder()->first();

        $newsData = [
            'Act of AI Regulation' => [
                [
                    'title' => 'FTC Announces New AI Regulations',
                    'status_id' => $statusId->id,
                    'upload_date' => '2023-05-01',
                    'description' => 'The FTC has announced new regulations to enhance AI transparency.',
                ],
                [
                    'title' => 'Impact of AI Regulation on Tech Companies',
                    'status_id' => $statusId->id,
                    'upload_date' => '2023-06-10',
                    'description' => 'Tech companies react to the new AI regulations from the FTC.',
                ],
                [
                    'title' => 'Public Response to AI Regulation Act',
                    'status_id' => $statusId->id,
                    'upload_date' => '2023-07-20',
                    'description' => 'Public opinion on the FTC’s new AI regulations.',
                ],
            ],
            'AI Ethics Act' => [
                [
                    'title' => 'European Commission Introduces AI Ethics Act',
                    'status_id' => $statusId->id,
                    'upload_date' => '2024-01-15',
                    'description' => 'The European Commission has introduced new ethical guidelines for AI.',
                ],
                [
                    'title' => 'AI Ethics Act to Affect EU AI Developers',
                    'status_id' => $statusId->id,
                    'upload_date' => '2024-02-05',
                    'description' => 'EU AI developers prepare for the new ethics regulations.',
                ],
                [
                    'title' => 'How the AI Ethics Act Impacts Consumers',
                    'status_id' => $statusId->id,
                    'upload_date' => '2024-03-10',
                    'description' => 'Exploring the impact of the AI Ethics Act on consumers in the EU.',
                ],
            ],
            'AI Surveillance Regulation' => [
                [
                    'title' => 'China’s AI Surveillance Regulation Announced',
                    'status_id' => $statusId->id,
                    'upload_date' => '2022-03-01',
                    'description' => 'China introduces new AI surveillance regulations aimed at enhancing public security.',
                ],
                [
                    'title' => 'Implications of AI Surveillance Regulation in China',
                    'status_id' => $statusId->id,
                    'upload_date' => '2022-04-15',
                    'description' => 'Analysis of how AI surveillance regulation will impact privacy and security in China.',
                ],
                [
                    'title' => 'Public Debate on AI Surveillance Regulation',
                    'status_id' => $statusId->id,
                    'upload_date' => '2022-05-20',
                    'description' => 'Public reactions and debates over the new AI surveillance regulations in China.',
                ],
            ],
            'AI Innovation Act' => [
                [
                    'title' => 'South Korea’s AI Innovation Act Launched',
                    'status_id' => $statusId->id,
                    'upload_date' => '2024-01-10',
                    'description' => 'South Korea introduces the AI Innovation Act to support AI startups.',
                ],
                [
                    'title' => 'Impact of AI Innovation Act on Korean Startups',
                    'status_id' => $statusId->id,
                    'upload_date' => '2024-02-20',
                    'description' => 'How the AI Innovation Act is expected to boost AI startups in South Korea.',
                ],
                [
                    'title' => 'AI Innovation Act: What It Means for Investors',
                    'status_id' => $statusId->id,
                    'upload_date' => '2024-03-15',
                    'description' => 'The implications of the AI Innovation Act for investors in South Korea.',
                ],
            ],
            'AI Accountability Framework' => [
                [
                    'title' => 'India’s New AI Accountability Framework',
                    'status_id' => $statusId->id,
                    'upload_date' => '2023-06-01',
                    'description' => 'India introduces a new framework to ensure accountability in AI systems.',
                ],
                [
                    'title' => 'AI Accountability Framework: Key Points',
                    'status_id' => $statusId->id,
                    'upload_date' => '2023-07-15',
                    'description' => 'Key points of the AI Accountability Framework and its impact on tech companies.',
                ],
                [
                    'title' => 'How AI Accountability Framework Will Affect Businesses',
                    'status_id' => $statusId->id,
                    'upload_date' => '2023-08-20',
                    'description' => 'Impact of the new AI Accountability Framework on Indian businesses.',
                ],
            ],
            'AI Fairness Act' => [
                [
                    'title' => 'Australia’s AI Fairness Act Unveiled',
                    'status_id' => $statusId->id,
                    'upload_date' => '2024-02-01',
                    'description' => 'The Australian Government introduces the AI Fairness Act to ensure equity in AI systems.',
                ],
                [
                    'title' => 'Impact of AI Fairness Act on AI Developers',
                    'status_id' => $statusId->id,
                    'upload_date' => '2024-03-10',
                    'description' => 'How the AI Fairness Act will influence AI development in Australia.',
                ],
                [
                    'title' => 'Public Response to AI Fairness Act',
                    'status_id' => $statusId->id,
                    'upload_date' => '2024-04-05',
                    'description' => 'Public opinion and reactions to the AI Fairness Act in Australia.',
                ],
            ],
            'AI Safety Protocol' => [
                [
                    'title' => 'Japan Introduces AI Safety Protocol',
                    'status_id' => $statusId->id,
                    'upload_date' => '2023-04-10',
                    'description' => 'Japan’s new protocol aims to ensure safe deployment of AI technologies.',
                ],
                [
                    'title' => 'AI Safety Protocol: What You Need to Know',
                    'status_id' => $statusId->id,
                    'upload_date' => '2023-05-25',
                    'description' => 'Overview of Japan’s AI Safety Protocol and its implications.',
                ],
                [
                    'title' => 'Impact of AI Safety Protocol on Tech Industry',
                    'status_id' => $statusId->id,
                    'upload_date' => '2023-06-30',
                    'description' => 'How Japan’s AI Safety Protocol will impact the technology industry.',
                ],
            ],
            'AI Transparency Act' => [
                [
                    'title' => 'Canada’s AI Transparency Act Enforced',
                    'status_id' => $statusId->id,
                    'upload_date' => '2024-02-15',
                    'description' => 'Canada introduces the AI Transparency Act to increase decision-making transparency.',
                ],
                [
                    'title' => 'AI Transparency Act: Key Details',
                    'status_id' => $statusId->id,
                    'upload_date' => '2024-03-05',
                    'description' => 'Details and key points of Canada’s AI Transparency Act.',
                ],
                [
                    'title' => 'Impact of AI Transparency Act on Businesses',
                    'status_id' => $statusId->id,
                    'upload_date' => '2024-04-01',
                    'description' => 'How Canadian businesses are adapting to the AI Transparency Act.',
                ],
            ],
            'AI Ethics and Governance Framework' => [
                [
                    'title' => 'India’s AI Ethics and Governance Framework Introduced',
                    'status_id' => $statusId->id,
                    'upload_date' => '2023-07-01',
                    'description' => 'India introduces a new framework for AI ethics and governance.',
                ],
                [
                    'title' => 'How AI Ethics and Governance Framework Affects AI Development',
                    'status_id' => $statusId->id,
                    'upload_date' => '2023-08-15',
                    'description' => 'Impact of India’s AI Ethics and Governance Framework on AI development.',
                ],
                [
                    'title' => 'Public Reaction to India’s AI Ethics Framework',
                    'status_id' => $statusId->id,
                    'upload_date' => '2023-09-20',
                    'description' => 'Public response and opinions on the AI Ethics and Governance Framework in India.',
                ],
            ],
            'AI Impact Assessment Act' => [
                [
                    'title' => 'Brazil Launches AI Impact Assessment Act',
                    'status_id' => $statusId->id,
                    'upload_date' => '2024-03-01',
                    'description' => 'Brazil introduces the AI Impact Assessment Act to mitigate AI’s societal impact.',
                ],
                [
                    'title' => 'Impact of AI Impact Assessment Act on Brazilian Tech Sector',
                    'status_id' => $statusId->id,
                    'upload_date' => '2024-04-10',
                    'description' => 'How the AI Impact Assessment Act will influence the tech sector in Brazil.',
                ],
                [
                    'title' => 'AI Impact Assessment Act: Key Implications',
                    'status_id' => $statusId->id,
                    'upload_date' => '2024-05-15',
                    'description' => 'Overview of the key implications of Brazil’s AI Impact Assessment Act.',
                ],
            ],
            'Global AI Collaboration Framework' => [
                [
                    'title' => 'UN Announces Global AI Collaboration Framework',
                    'status_id' => $statusId->id,
                    'upload_date' => '2024-04-01',
                    'description' => 'The UN introduces a framework to foster global AI collaboration.',
                ],
                [
                    'title' => 'Global AI Collaboration Framework: What It Means',
                    'status_id' => $statusId->id,
                    'upload_date' => '2024-05-10',
                    'description' => 'Implications of the UN’s Global AI Collaboration Framework for international AI research.',
                ],
                [
                    'title' => 'Global Response to AI Collaboration Framework',
                    'status_id' => $statusId->id,
                    'upload_date' => '2024-06-15',
                    'description' => 'How different countries are responding to the UN’s Global AI Collaboration Framework.',
                ],
            ],
            'AI Research and Development Act' => [
                [
                    'title' => 'US National Science Foundation Unveils AI Research and Development Act',
                    'status_id' => $statusId->id,
                    'upload_date' => '2024-05-01',
                    'description' => 'The US NSF introduces the AI Research and Development Act to support AI innovation.',
                ],
                [
                    'title' => 'Impact of AI Research and Development Act on US Tech Industry',
                    'status_id' => $statusId->id,
                    'upload_date' => '2024-06-20',
                    'description' => 'How the AI Research and Development Act will affect the US tech industry.',
                ],
                [
                    'title' => 'Funding Opportunities under AI Research and Development Act',
                    'status_id' => $statusId->id,
                    'upload_date' => '2024-07-15',
                    'description' => 'Funding opportunities and grants available through the AI Research and Development Act.',
                ],
            ],
        ];

        foreach ($policies as $policy) {
            $country = Country::inRandomOrder()->first();
            $status = Status::inRandomOrder()->first();
            $createdPolicy = AiPolicyTracker::create(array_merge(
                $policy,
                [
                    'country_id' => $country->id,
                    'status_id' => $status->id,
                ]
            ));

            // Log create the AI policy activity
            $activity_name = 'added data';
            $description = 'A new AI policy, <b>' . $createdPolicy->ai_policy_name . '</b>, has been added.';
            AIPolicyActivityLog::create([
                'user_id' => 1,
                'ai_policy_tracker_id' => $createdPolicy->id,
                'activity_name' => $activity_name,
                'description' => $description,
                'created_at' => Carbon::parse('2020-09-10')
            ]);

            // Log update the AI policy activity
            $activity_name = 'updated data';
            $description = 'A new AI policy, <b>' . $createdPolicy->ai_policy_name . '</b>, has been updated.';
            AIPolicyActivityLog::create([
                'user_id' => 1,
                'ai_policy_tracker_id' => $createdPolicy->id,
                'activity_name' => $activity_name,
                'description' => $description,
            ]);

            // Fetch the related news articles for the policy
            if (isset($newsData[$policy['ai_policy_name']])) {
                foreach ($newsData[$policy['ai_policy_name']] as $news) {
                    $createdPolicy->news()->create(
                        $news,
                    );
                }
            }
        }
    }
}
