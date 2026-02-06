<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * childcourse.php
 *
 * @package   mod_childcourse
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

$string['autoenrol'] = 'Matrícula automática ao acessar';
$string['autoenrol_help'] = 'Se ativado, o plugin matriculará automaticamente o usuário no curso filho quando ele o abrir por meio desta atividade. As matrículas são criadas usando uma instância dedicada de Matrícula manual para que possam ser rastreadas e revertidas com segurança posteriormente (dependendo da política de remoção). Se desativado, o plugin não tentará matricular usuários automaticamente.';
$string['childcourse'] = 'Curso filho';
$string['childcourse:addinstance'] = 'Adicionar uma nova atividade de curso filho';
$string['childcourse:manage'] = 'Gerenciar configurações do curso filho';
$string['childcourse:sync'] = 'Sincronizar nota e conclusão do curso filho';
$string['childcourse:view'] = 'Ver atividade de curso filho';
$string['childcourse_help'] = 'Selecione o curso que será vinculado a esta atividade. Essa escolha controla todas as configurações específicas das regras (grupos, regras de conclusão, seletores de atividades, sincronização de notas). Depois que a atividade for salva, o curso filho torna-se imutável para manter consistentes os mapeamentos e o histórico de sincronização.';
$string['completionmissing'] = 'A conclusão do curso filho não está ativada.';
$string['completionrule'] = 'Regra de conclusão baseado no curso filho';
$string['completionrule_allactivities'] = 'Concluir quando 100% das atividades monitoradas forem concluídas';
$string['completionrule_coursecompleted'] = 'Concluir quando o curso filho for concluído';
$string['completionrule_help'] = 'Define como esta atividade é automaticamente marcada como concluída com base no progresso do usuário no curso filho.

- **Não fazer nada:** a conclusão desta atividade não tem referência com a conclusão do curso filho.
- **Quando o curso filho for concluído:** Assim que o curso filho for concluído a atividade também é concluída.
- **Quando 100% das atividades rastreadas forem concluídas:** todas as atividades no curso filho com rastreamento de conclusão ativado devem ser concluídas para que esta atividade seja concluída.';
$string['completionrule_none'] = 'Não fazer nada';
$string['enrolinstancename'] = 'Vínculo do curso filho #{$a}';
$string['error_manualenrolnotavailable'] = 'O plugin de Matrícula manual não está disponível.';
$string['gradebookmissing'] = 'O livro de notas do curso filho não está configurado (o total do curso está ausente).';
$string['hideinmycourses'] = 'Ocultar curso filho em Meus cursos';
$string['hideinmycourses_help'] = 'Se ativado, usuários matriculados por esta atividade terão o curso filho ocultado em menu "Meus cursos". Isso ajuda a forçar a navegação por este curso. Esta configuração afeta apenas usuários matriculados por este plugin (rastreados pelo plugin).';
$string['inheritgroups'] = 'Herdar grupos do curso pai';
$string['inheritgroups_help'] = 'Se ativado, o plugin tentará replicar as associações de grupo do usuário do curso pai para o curso filho, correspondendo pelos nomes dos grupos. Se um nome de grupo não existir no curso filho, ele poderá ser criado. Isso é aplicado durante a matrícula automática. Não é uma sincronização contínua, a menos que você implemente posteriormente uma rotina dedicada de re-sincronização.';
$string['keeprole'] = 'Manter papel (estudante/professor)';
$string['keeprole_help'] = 'Se ativado, o plugin tentará manter uma paridade simplificada de papéis: usuários com permissões de nível de professor no curso pai serão matriculados como professor (editingteacher/teacher quando disponível); caso contrário, como estudante. Isso não copia papéis personalizados nem atribuições complexas de papéis.';
$string['label_childcourse'] = 'Curso filho';
$string['label_lastsynccompletion'] = 'Última sincronização de conclusão';
$string['label_lastsyncgrade'] = 'Última sincronização de notas';
$string['lastsync'] = 'Última sincronização';
$string['lockedcoursewarning'] = 'O curso filho não pode ser alterado após salvar.';
$string['manage_header_actions'] = 'Ações';
$string['manage_header_name'] = 'Nome';
$string['modulename'] = 'Curso filho';
$string['modulenameplural'] = 'Cursos filho';
$string['never'] = 'Nunca';
$string['nogroup'] = 'Sem grupo';
$string['openchildcourse'] = 'Abrir curso filho';
$string['opennewtab'] = 'Abrir em uma nova aba';
$string['opennewtab_help'] = 'Se ativado, o botão que abre o curso filho em nova aba. Isso não altera o comportamento de matrícula nem de sincronização, apenas a forma como o curso é aberto para o usuário.';
$string['pluginadministration'] = 'Child course administration';
$string['pluginname'] = 'Curso filho';
$string['privacy:metadata:childcourse_map'] = 'Armazena dados de mapeamento criados pela atividade de curso vinculada para permitir o cancelamento seguro de matrícula e auditoria.';
$string['privacy:metadata:childcourse_map:childcourseid'] = 'O ID do curso filho que foi vinculado.';
$string['privacy:metadata:childcourse_map:childcourseinstanceid'] = 'O ID da instância da atividade de curso vinculada.';
$string['privacy:metadata:childcourse_map:groupidsjson'] = 'A lista de IDs de grupos do curso filho atribuídos pelo plugin (JSON).';
$string['privacy:metadata:childcourse_map:hiddenprefset'] = 'Indica se o plugin definiu a preferência para ocultar o curso filho em Meus cursos.';
$string['privacy:metadata:childcourse_map:manualenrolid'] = 'O ID da instância de matrícula usado pelo plugin para matricular o usuário.';
$string['privacy:metadata:childcourse_map:parentcourseid'] = 'O ID do curso pai onde a atividade existe.';
$string['privacy:metadata:childcourse_map:roleid'] = 'O ID do papel atribuído pelo plugin no curso filho.';
$string['privacy:metadata:childcourse_map:timeenrolled'] = 'O momento em que o usuário foi matriculado por meio do vínculo.';
$string['privacy:metadata:childcourse_map:timemodified'] = 'O horário da última modificação do registro de mapeamento.';
$string['privacy:metadata:childcourse_map:userid'] = 'O ID do usuário matriculado por meio do vínculo.';
$string['privacy:metadata:childcourse_state'] = 'Armazena o estado em cache por usuário para dar suporte à sincronização incremental de notas e conclusão.';
$string['privacy:metadata:childcourse_state:childcourseinstanceid'] = 'O ID da instância da atividade de curso vinculada.';
$string['privacy:metadata:childcourse_state:coursecompleted'] = 'Indicador em cache que informa se a regra de conclusão foi satisfeita para o usuário.';
$string['privacy:metadata:childcourse_state:coursecompletiontimemodified'] = 'O carimbo de data/hora da última modificação dos dados de conclusão de origem para sincronização incremental.';
$string['privacy:metadata:childcourse_state:finalgrade'] = 'Nota em cache (percentual) sincronizada do total do curso filho.';
$string['privacy:metadata:childcourse_state:grade_source'] = 'O identificador da origem da nota (por exemplo: course_total).';
$string['privacy:metadata:childcourse_state:gradeitemtimemodified'] = 'O carimbo de data/hora da última modificação do item de nota de origem para sincronização incremental.';
$string['privacy:metadata:childcourse_state:timemodified'] = 'O horário da última modificação da linha de estado em cache.';
$string['privacy:metadata:childcourse_state:userid'] = 'O ID do usuário.';
$string['privacy:metadata:userpreference:block_myoverview_hidden_course'] = 'Uma preferência do usuário usada para ocultar um curso filho em Meus cursos (padrão do nome da preferência: block_myoverview_hidden_course_{courseid}).';
$string['settings_heading'] = 'Configurações do curso filho';
$string['syncdone'] = 'Sincronização concluída.';
$string['syncnow'] = 'Sincronizar agora';
$string['targetgroup'] = 'Matricular no grupo';
$string['targetgroup_help'] = 'Se selecionado, o usuário será adicionado a este grupo específico no curso filho no momento da matrícula automática. O grupo deve existir no curso filho. Se "Herdar grupos do curso pai" também estiver ativado, ambos os comportamentos se aplicam (o grupo selecionado e os grupos herdados).';
$string['unenrolaction'] = 'Quando o vínculo for removido';
$string['unenrolaction_help'] = 'Controla o que acontece com as matrículas criadas por esta atividade quando a atividade vinculada é excluída. "Desmatricular" removerá apenas as matrículas que foram criadas por esta atividade (rastreada na tabela de mapeamento). "Manter matrículas" deixará os usuários matriculados no curso filho.';
$string['unenrolaction_keep'] = 'Manter matrículas';
$string['unenrolaction_unenrol'] = 'Desmatricular usuários matriculados por este vínculo';
