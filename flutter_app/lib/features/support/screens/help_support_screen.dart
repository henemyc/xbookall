import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:gymxbook/core/widgets/ui.dart';
import 'package:gymxbook/features/reports/screens/report_bug_screen.dart';

class HelpSupportScreen extends ConsumerStatefulWidget {
  const HelpSupportScreen({super.key});

  @override
  ConsumerState<HelpSupportScreen> createState() => _HelpSupportScreenState();
}

class _HelpSupportScreenState extends ConsumerState<HelpSupportScreen> {
  final _search = TextEditingController();
  String _category = 'All';
  bool _hindi = false;

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  List<_HelpArticle> get _filtered {
    final query = _search.text.trim().toLowerCase();
    return _articles.where((article) {
      final categoryOk = _category == 'All' || article.category == _category;
      if (!categoryOk) return false;
      if (query.isEmpty) return true;
      final corpus = '${article.titleEn} ${article.titleHi} ${article.keywords.join(' ')} ${article.answerEn} ${article.answerHi}'.toLowerCase();
      return corpus.contains(query);
    }).toList();
  }

  @override
  Widget build(BuildContext context) {
    final articles = _filtered;
    final categories = ['All', ..._articles.map((e) => e.category).toSet()];
    return Scaffold(
      appBar: AppBar(
        title: const Text('Help & Support'),
        actions: [
          Padding(
            padding: const EdgeInsets.only(right: 12),
            child: SegmentedButton<bool>(
              segments: const [
                ButtonSegment(value: false, label: Text('EN')),
                ButtonSegment(value: true, label: Text('हिं')),
              ],
              selected: {_hindi},
              showSelectedIcon: false,
              style: ButtonStyle(visualDensity: VisualDensity.compact, textStyle: WidgetStateProperty.all(const TextStyle(fontSize: 11, fontWeight: FontWeight.w700))),
              onSelectionChanged: (value) => setState(() => _hindi = value.first),
            ),
          ),
        ],
      ),
      body: ListView(
        padding: EdgeInsets.fromLTRB(16, 10, 16, context.navSpace + 24),
        children: [
          _hero(),
          const SizedBox(height: 18),
          TextField(
            controller: _search,
            onChanged: (_) => setState(() {}),
            decoration: InputDecoration(
              hintText: _hindi ? 'अपना सवाल खोजें...' : 'Search your question...',
              prefixIcon: const Icon(Icons.search_rounded),
              suffixIcon: _search.text.isEmpty
                  ? null
                  : IconButton(onPressed: () { _search.clear(); setState(() {}); }, icon: const Icon(Icons.close_rounded)),
            ),
          ),
          const SizedBox(height: 14),
          SizedBox(
            height: 38,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              itemCount: categories.length,
              separatorBuilder: (_, __) => const SizedBox(width: 8),
              itemBuilder: (_, index) {
                final category = categories[index];
                final selected = category == _category;
                return ChoiceChip(
                  label: Text(category),
                  selected: selected,
                  onSelected: (_) => setState(() => _category = category),
                  selectedColor: AppTheme.brand,
                  labelStyle: context.typo.labelMedium?.copyWith(color: selected ? Colors.white : context.tokens.textSecondary, fontWeight: FontWeight.w700),
                );
              },
            ),
          ),
          const SizedBox(height: 22),
          if (_search.text.isEmpty && _category == 'All') ...[
            Text(_hindi ? 'त्वरित सहायता' : 'Quick Help', style: context.typo.titleLarge),
            const SizedBox(height: 12),
            _quickGrid(),
            const SizedBox(height: 26),
          ],
          Row(children: [
            Expanded(child: Text(_hindi ? 'सहायता लेख' : 'Help Articles', style: context.typo.titleLarge)),
            Text('${articles.length} ${_hindi ? 'परिणाम' : 'articles'}', style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary)),
          ]),
          const SizedBox(height: 12),
          if (articles.isEmpty)
            _emptySearch()
          else
            ...articles.map((article) => Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: _articleCard(article),
            )),
          const SizedBox(height: 16),
          _supportCard(),
        ],
      ),
    );
  }

  Widget _hero() => Container(
    padding: const EdgeInsets.all(20),
    decoration: BoxDecoration(gradient: AppTheme.darkHeroGradient, borderRadius: BorderRadius.circular(26)),
    child: Stack(children: [
      Positioned(right: -12, top: -16, child: Icon(Icons.support_agent_rounded, size: 118, color: AppTheme.brand.withOpacity(.16))),
      Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(_hindi ? 'GymXBook सहायता केंद्र' : 'GymXBook Help Center', style: context.typo.titleLarge?.copyWith(color: Colors.white, fontWeight: FontWeight.w800)),
        const SizedBox(height: 8),
        Text(_hindi ? 'सदस्य, भुगतान, उपस्थिति और सेटिंग्स के लिए आसान चरण-दर-चरण मार्गदर्शन।' : 'Practical step-by-step guidance for members, payments, attendance and settings.', style: context.typo.bodySmall?.copyWith(color: Colors.white.withOpacity(.72), height: 1.5)),
      ]),
    ]),
  );

  Widget _quickGrid() {
    final quick = _articles.where((article) => article.quick).take(6).toList();
    return GridView.builder(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(crossAxisCount: 3, mainAxisSpacing: 10, crossAxisSpacing: 10, childAspectRatio: 1.08),
      itemCount: quick.length,
      itemBuilder: (_, index) {
        final article = quick[index];
        return Pressable(
          radius: 18,
          onTap: () => _openArticle(article),
          child: Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(color: context.tokens.surface, borderRadius: BorderRadius.circular(18), border: Border.all(color: context.tokens.border), boxShadow: context.subtleShadow),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Icon(article.icon, color: AppTheme.brand, size: 22),
              const Spacer(),
              Text(_hindi ? article.titleHi : article.titleEn, maxLines: 2, overflow: TextOverflow.ellipsis, style: context.typo.labelMedium?.copyWith(color: context.tokens.text, fontWeight: FontWeight.w700, height: 1.2)),
            ]),
          ),
        );
      },
    );
  }

  Widget _articleCard(_HelpArticle article) => SurfaceCard(
    padding: const EdgeInsets.all(14),
    onTap: () => _openArticle(article),
    child: Row(children: [
      IconBadge(article.icon, color: AppTheme.brand, size: 44, iconSize: 21),
      const SizedBox(width: 12),
      Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(_hindi ? article.titleHi : article.titleEn, style: context.typo.titleSmall?.copyWith(fontSize: 14.5)),
        const SizedBox(height: 3),
        Text(_hindi ? article.summaryHi : article.summaryEn, maxLines: 2, overflow: TextOverflow.ellipsis, style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary, fontSize: 11.5)),
        const SizedBox(height: 7),
        Text(article.category, style: context.typo.labelSmall?.copyWith(color: AppTheme.brand, letterSpacing: .4)),
      ])),
      Icon(Icons.chevron_right_rounded, color: context.tokens.textTertiary),
    ]),
  );

  Widget _emptySearch() => Center(child: Padding(
    padding: const EdgeInsets.all(30),
    child: Column(children: [
      IconBadge(Icons.search_off_rounded, color: AppTheme.warning, size: 64, iconSize: 30),
      const SizedBox(height: 14),
      Text(_hindi ? 'कोई उत्तर नहीं मिला' : 'No matching answer found', style: context.typo.titleMedium),
      const SizedBox(height: 6),
      Text(_hindi ? 'अलग शब्दों से खोजें या सहायता अनुरोध बनाएं।' : 'Try different words or create a support request.', textAlign: TextAlign.center, style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary)),
    ]),
  ));

  Widget _supportCard() => SurfaceCard(
    color: AppTheme.brand.withOpacity(.07),
    border: Border.all(color: AppTheme.brand.withOpacity(.18)),
    child: Row(children: [
      IconBadge(Icons.bug_report_rounded, color: AppTheme.brand),
      const SizedBox(width: 12),
      Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(_hindi ? 'फिर भी सहायता चाहिए?' : 'Still need help?', style: context.typo.titleSmall),
        Text(_hindi ? 'सहायता अनुरोध या बग रिपोर्ट भेजें।' : 'Send a support request or bug report.', style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary)),
      ])),
      TextButton(onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const ReportBugScreen())), child: Text(_hindi ? 'रिपोर्ट करें' : 'Report')),
    ]),
  );

  void _openArticle(_HelpArticle article) {
    showAppSheet(context, child: SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(22, 8, 22, 28),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [IconBadge(article.icon, color: AppTheme.brand, size: 48, iconSize: 23), const SizedBox(width: 12), Expanded(child: Text(_hindi ? article.titleHi : article.titleEn, style: context.typo.titleLarge))]),
        const SizedBox(height: 20),
        Text(_hindi ? article.answerHi : article.answerEn, style: context.typo.bodyMedium?.copyWith(color: context.tokens.textSecondary, height: 1.65)),
        if (article.tipEn.isNotEmpty) ...[
          const SizedBox(height: 18),
          Container(padding: const EdgeInsets.all(14), decoration: BoxDecoration(color: AppTheme.brand.withOpacity(.08), borderRadius: BorderRadius.circular(14)), child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [const Icon(Icons.lightbulb_outline_rounded, color: AppTheme.brand, size: 20), const SizedBox(width: 10), Expanded(child: Text(_hindi ? article.tipHi : article.tipEn, style: context.typo.bodySmall?.copyWith(color: context.tokens.textSecondary, height: 1.45)))])),
        ],
      ]),
    ));
  }
}

class _HelpArticle {
  final String category;
  final String titleEn;
  final String titleHi;
  final String summaryEn;
  final String summaryHi;
  final String answerEn;
  final String answerHi;
  final String tipEn;
  final String tipHi;
  final List<String> keywords;
  final IconData icon;
  final bool quick;
  const _HelpArticle({required this.category, required this.titleEn, required this.titleHi, required this.summaryEn, required this.summaryHi, required this.answerEn, required this.answerHi, required this.tipEn, required this.tipHi, required this.keywords, required this.icon, this.quick = false});
}

const _articles = <_HelpArticle>[
  _HelpArticle(category: 'Members', quick: true, icon: Icons.person_add_alt_1_rounded, titleEn: 'How do I add a member?', titleHi: 'नया सदस्य कैसे जोड़ें?', summaryEn: 'Create a member with plan and payment details.', summaryHi: 'प्लान और भुगतान विवरण के साथ सदस्य जोड़ें।', answerEn: '1. Open Members from the sidebar.\n2. Tap Add Member.\n3. Enter name, phone, address and optional health details.\n4. Select a membership plan.\n5. Add registration fee or payment if received.\n6. Assign a trainer or class if required.\n7. Tap Save Member.\n\nThe member profile, membership dates and invoice are created from this flow.', answerHi: '1. साइडबार से Members खोलें।\n2. Add Member दबाएं।\n3. नाम, फोन, पता और आवश्यक विवरण भरें।\n4. सदस्यता प्लान चुनें।\n5. प्राप्त भुगतान या रजिस्ट्रेशन शुल्क भरें।\n6. जरूरत हो तो ट्रेनर या क्लास असाइन करें।\n7. Save Member दबाएं।\n\nइससे सदस्य प्रोफाइल, सदस्यता तिथि और इनवॉइस बनता है।', tipEn: 'Always select a plan before saving. It controls membership expiry and billing.', tipHi: 'सेव करने से पहले प्लान जरूर चुनें। इससे एक्सपायरी और बिलिंग सही रहती है।', keywords: ['add member', 'new member', 'register member', 'trainee']),
  _HelpArticle(category: 'Members', quick: true, icon: Icons.autorenew_rounded, titleEn: 'How do I renew membership?', titleHi: 'सदस्यता रिन्यू कैसे करें?', summaryEn: 'Extend plan dates and record renewal payment.', summaryHi: 'प्लान की तिथि बढ़ाएं और रिन्यूअल भुगतान दर्ज करें।', answerEn: '1. Open Members and select the member.\n2. Tap Renew.\n3. Select the new membership plan.\n4. Confirm start and expiry dates.\n5. Enter received payment amount and method.\n6. Confirm renewal.\n\nA renewal invoice and payment transaction are created automatically.', answerHi: '1. Members खोलकर सदस्य चुनें।\n2. Renew दबाएं।\n3. नया सदस्यता प्लान चुनें।\n4. प्रारंभ और एक्सपायरी तिथि जांचें।\n5. भुगतान राशि और तरीका भरें।\n6. रिन्यूअल कन्फर्म करें।\n\nरिन्यूअल इनवॉइस और भुगतान लेनदेन अपने आप बनता है।', tipEn: 'Do not edit expiry dates manually when renewal payment should be recorded; use Renew.', tipHi: 'जब रिन्यूअल भुगतान रिकॉर्ड करना हो तो सिर्फ एक्सपायरी तिथि एडिट न करें, Renew का उपयोग करें।', keywords: ['renew', 'renewal', 'expiry', 'extend plan']),
  _HelpArticle(category: 'Attendance', quick: true, icon: Icons.qr_code_scanner_rounded, titleEn: 'How does Gym QR attendance work?', titleHi: 'Gym QR उपस्थिति कैसे काम करती है?', summaryEn: 'Members scan the gym QR to check in and out.', summaryHi: 'सदस्य चेक-इन और चेक-आउट के लिए Gym QR स्कैन करते हैं।', answerEn: '1. Open Gym QR from the sidebar.\n2. Display the QR at your reception or entrance.\n3. Member opens Scan QR in their app.\n4. Successful scan creates check-in.\n5. A later scan checks out the member when applicable.\n\nYou can also review or correct attendance from Attendance screen.', answerHi: '1. साइडबार से Gym QR खोलें।\n2. QR को रिसेप्शन या प्रवेश स्थान पर दिखाएं।\n3. सदस्य अपने ऐप में Scan QR खोलता है।\n4. सफल स्कैन से चेक-इन बनता है।\n5. बाद में स्कैन करने पर चेक-आउट होता है।\n\nAttendance स्क्रीन से रिकॉर्ड देख या सुधार सकते हैं।', tipEn: 'Do not share the QR image publicly. Keep it at the gym entrance only.', tipHi: 'QR इमेज सार्वजनिक रूप से शेयर न करें। इसे केवल जिम प्रवेश पर रखें।', keywords: ['qr', 'attendance', 'check in', 'check out']),
  _HelpArticle(category: 'Attendance', icon: Icons.fact_check_rounded, titleEn: 'How do I add manual attendance?', titleHi: 'मैनुअल उपस्थिति कैसे जोड़ें?', summaryEn: 'Use manual attendance if a member cannot scan QR.', summaryHi: 'अगर सदस्य QR स्कैन नहीं कर सकता तो मैनुअल उपस्थिति जोड़ें।', answerEn: 'Open Attendance, search for the member, then use the manual check-in action. Select the correct time if needed and save. Use this only when QR scanning is unavailable or reception staff needs to correct a missed scan.', answerHi: 'Attendance खोलें, सदस्य खोजें और मैनुअल चेक-इन विकल्प चुनें। जरूरत हो तो सही समय चुनें और सेव करें। इसका उपयोग केवल QR स्कैन उपलब्ध न होने या मिस्ड स्कैन सुधारने के लिए करें।', tipEn: 'Avoid duplicate records by checking whether the member already checked in today.', tipHi: 'डुप्लीकेट रिकॉर्ड से बचने के लिए पहले जांचें कि सदस्य आज पहले से चेक-इन तो नहीं है।', keywords: ['manual attendance', 'manual checkin', 'missed attendance']),
  _HelpArticle(category: 'Finance', quick: true, icon: Icons.receipt_long_rounded, titleEn: 'How do I create an invoice?', titleHi: 'इनवॉइस कैसे बनाएं?', summaryEn: 'Create a bill for membership, products or custom services.', summaryHi: 'सदस्यता, उत्पाद या अन्य सेवाओं के लिए बिल बनाएं।', answerEn: '1. Open Invoices.\n2. Tap Create Invoice.\n3. Select the member.\n4. Add one or more line items.\n5. Enter payment amount if money was received.\n6. Save the invoice.\n\nPaid/partial/unpaid status is calculated from invoice amount and payment history.', answerHi: '1. Invoices खोलें।\n2. Create Invoice दबाएं।\n3. सदस्य चुनें।\n4. एक या अधिक आइटम जोड़ें।\n5. भुगतान मिला हो तो राशि भरें।\n6. इनवॉइस सेव करें।\n\nPaid, Partial या Unpaid स्थिति राशि और भुगतान हिस्ट्री से तय होती है।', tipEn: 'Use separate line items for registration fee, membership fee, products and classes.', tipHi: 'रजिस्ट्रेशन फीस, सदस्यता फीस, उत्पाद और क्लास के लिए अलग लाइन आइटम रखें।', keywords: ['invoice', 'bill', 'receipt', 'payment']),
  _HelpArticle(category: 'Finance', icon: Icons.payments_rounded, titleEn: 'How do I record a payment?', titleHi: 'भुगतान कैसे दर्ज करें?', summaryEn: 'Add payment to an existing unpaid or partial invoice.', summaryHi: 'Unpaid या Partial इनवॉइस में भुगतान जोड़ें।', answerEn: 'Open the invoice, tap Add Payment, enter amount, payment method and date, then save. The invoice status updates automatically. Do not create a second invoice only to record a later payment.', answerHi: 'इनवॉइस खोलें, Add Payment दबाएं, राशि, भुगतान तरीका और तिथि भरें, फिर सेव करें। इनवॉइस स्थिति अपने आप अपडेट होती है। बाद के भुगतान के लिए दूसरा इनवॉइस न बनाएं।', tipEn: 'Use the actual payment method such as cash, UPI, card or bank for accurate reports.', tipHi: 'सही रिपोर्ट के लिए वास्तविक भुगतान तरीका जैसे Cash, UPI, Card या Bank चुनें।', keywords: ['payment', 'add payment', 'partial payment', 'due']),
  _HelpArticle(category: 'Finance', icon: Icons.account_balance_wallet_rounded, titleEn: 'How do I add an expense?', titleHi: 'खर्च कैसे जोड़ें?', summaryEn: 'Record rent, salary, utility or other gym expenses.', summaryHi: 'किराया, वेतन, बिजली या अन्य जिम खर्च दर्ज करें।', answerEn: 'Open Expenses, tap Add Expense, choose category, amount, date and optional note, then save. Expenses are included in monthly revenue versus expense reports.', answerHi: 'Expenses खोलें, Add Expense दबाएं, श्रेणी, राशि, तिथि और वैकल्पिक नोट भरें, फिर सेव करें। खर्च मासिक आय और खर्च रिपोर्ट में शामिल होते हैं।', tipEn: 'Record expenses on the actual expense date for accurate monthly reporting.', tipHi: 'सही मासिक रिपोर्ट के लिए खर्च वास्तविक खर्च की तिथि पर दर्ज करें।', keywords: ['expense', 'cost', 'rent', 'salary']),
  _HelpArticle(category: 'Management', quick: true, icon: Icons.sports_martial_arts_rounded, titleEn: 'How do I add a trainer?', titleHi: 'ट्रेनर कैसे जोड़ें?', summaryEn: 'Create trainer profile and assign members later.', summaryHi: 'ट्रेनर प्रोफाइल बनाएं और बाद में सदस्य असाइन करें।', answerEn: 'Open Trainers, tap Add Trainer, enter trainer name, phone, qualification, specialization, address and joining details. Save the trainer. You can assign the trainer while adding/editing a member.', answerHi: 'Trainers खोलें, Add Trainer दबाएं, नाम, फोन, योग्यता, विशेषज्ञता, पता और जॉइनिंग विवरण भरें। ट्रेनर सेव करें। सदस्य जोड़ते या एडिट करते समय ट्रेनर असाइन कर सकते हैं।', tipEn: 'Trainer module availability depends on your subscription plan.', tipHi: 'Trainer मॉड्यूल की उपलब्धता आपके सब्सक्रिप्शन प्लान पर निर्भर करती है।', keywords: ['trainer', 'add trainer', 'assign trainer']),
  _HelpArticle(category: 'Management', icon: Icons.card_membership_rounded, titleEn: 'How do I create membership plans?', titleHi: 'सदस्यता प्लान कैसे बनाएं?', summaryEn: 'Create plans before adding members.', summaryHi: 'सदस्य जोड़ने से पहले प्लान बनाएं।', answerEn: 'Open Plans, tap Add Plan, enter title, package duration and amount, then save. Plans control the default membership duration and billing amount when you add or renew members.', answerHi: 'Plans खोलें, Add Plan दबाएं, प्लान नाम, अवधि और राशि भरें, फिर सेव करें। सदस्य जोड़ते या रिन्यू करते समय प्लान से अवधि और बिलिंग तय होती है।', tipEn: 'Use clear names such as Monthly, Quarterly, Half-Yearly and Annual.', tipHi: 'Monthly, Quarterly, Half-Yearly और Annual जैसे स्पष्ट नाम रखें।', keywords: ['plan', 'membership plan', 'create plan']),
  _HelpArticle(category: 'Management', icon: Icons.self_improvement_rounded, titleEn: 'How do I manage classes?', titleHi: 'क्लास कैसे मैनेज करें?', summaryEn: 'Create class schedules and assign members.', summaryHi: 'क्लास शेड्यूल बनाएं और सदस्य असाइन करें।', answerEn: 'Open Classes, create a class with title, fee and schedule. Add days and timing. Then assign members from the class screen or member profile. Classes may require Silver or Gold plan depending on your subscription.', answerHi: 'Classes खोलें, नाम, फीस और शेड्यूल के साथ क्लास बनाएं। दिन और समय जोड़ें। फिर क्लास स्क्रीन या सदस्य प्रोफाइल से सदस्य असाइन करें। Classes के लिए Silver या Gold प्लान की जरूरत हो सकती है।', tipEn: 'Keep class schedules current so members and trainers see the correct timing.', tipHi: 'क्लास शेड्यूल अपडेट रखें ताकि सदस्य और ट्रेनर सही समय देख सकें।', keywords: ['class', 'schedule', 'batch']),
  _HelpArticle(category: 'Management', icon: Icons.lock_outline_rounded, titleEn: 'How do I assign a locker?', titleHi: 'लॉकर कैसे असाइन करें?', summaryEn: 'Create lockers and assign available locker to a member.', summaryHi: 'लॉकर बनाएं और उपलब्ध लॉकर सदस्य को असाइन करें।', answerEn: 'Open Lockers, add lockers if needed, select an available locker, search the member and assign it. When a member leaves, unassign the locker so it becomes available again.', answerHi: 'Lockers खोलें, जरूरत हो तो लॉकर जोड़ें, उपलब्ध लॉकर चुनें, सदस्य खोजें और असाइन करें। सदस्य जाने पर लॉकर अनअसाइन करें ताकि वह फिर उपलब्ध हो जाए।', tipEn: 'Locker module availability depends on your subscription plan.', tipHi: 'Locker मॉड्यूल की उपलब्धता आपके सब्सक्रिप्शन प्लान पर निर्भर करती है।', keywords: ['locker', 'assign locker', 'available locker']),
  _HelpArticle(category: 'Communication', quick: true, icon: Icons.campaign_rounded, titleEn: 'How do I create a notice?', titleHi: 'नोटिस कैसे बनाएं?', summaryEn: 'Publish an announcement for your gym users.', summaryHi: 'जिम उपयोगकर्ताओं के लिए घोषणा प्रकाशित करें।', answerEn: 'Open Notices, tap Add Notice, enter a clear title and detailed description, then publish. Members and other gym users can see it in their Notices section. If push notifications are enabled, they can receive a New Notice alert.', answerHi: 'Notices खोलें, Add Notice दबाएं, स्पष्ट शीर्षक और विवरण भरें, फिर प्रकाशित करें। सदस्य और अन्य जिम उपयोगकर्ता इसे Notices सेक्शन में देख सकते हैं। Push notification सक्रिय होने पर उन्हें New Notice अलर्ट मिल सकता है।', tipEn: 'Use short titles and include date/time for closure, holiday or event notices.', tipHi: 'छोटे शीर्षक रखें और बंद, छुट्टी या इवेंट नोटिस में तिथि/समय जरूर शामिल करें।', keywords: ['notice', 'announcement', 'message']),
  _HelpArticle(category: 'Settings', icon: Icons.business_rounded, titleEn: 'How do I update gym profile?', titleHi: 'जिम प्रोफाइल कैसे अपडेट करें?', summaryEn: 'Update business name, contact and address.', summaryHi: 'जिम का नाम, संपर्क और पता अपडेट करें।', answerEn: 'Open Settings, then Gym Profile. Update business name, contact phone, email and address, then save. This information appears in invoices, dashboard and gym identity areas.', answerHi: 'Settings खोलें, फिर Gym Profile पर जाएं। जिम नाम, संपर्क फोन, ईमेल और पता अपडेट करें, फिर सेव करें। यह जानकारी इनवॉइस, डैशबोर्ड और जिम पहचान क्षेत्रों में दिखती है।', tipEn: 'Personal Profile changes your own owner account; Gym Profile changes business information.', tipHi: 'Personal Profile आपके मालिक खाते को बदलता है; Gym Profile व्यवसाय की जानकारी बदलता है।', keywords: ['gym profile', 'business name', 'address', 'settings']),
  _HelpArticle(category: 'Settings', icon: Icons.workspace_premium_rounded, titleEn: 'How do subscription plans work?', titleHi: 'सब्सक्रिप्शन प्लान कैसे काम करते हैं?', summaryEn: 'Understand Bronze, Silver and Gold feature access.', summaryHi: 'Bronze, Silver और Gold फीचर एक्सेस समझें।', answerEn: 'Open Settings or Subscription to view your current plan and expiry. Plan level controls modules such as Trainers, Classes, Lockers, staff limits and reports. Renew or upgrade before expiry to avoid feature interruption.', answerHi: 'Settings या Subscription खोलकर अपना वर्तमान प्लान और एक्सपायरी देखें। प्लान स्तर Trainers, Classes, Lockers, staff limits और reports जैसे मॉड्यूल नियंत्रित करता है। एक्सपायरी से पहले रिन्यू या अपग्रेड करें।', tipEn: 'If a module is missing from the sidebar, check your plan feature access first.', tipHi: 'अगर कोई मॉड्यूल साइडबार में नहीं दिख रहा है तो पहले अपने प्लान फीचर एक्सेस की जांच करें।', keywords: ['subscription', 'bronze', 'silver', 'gold', 'upgrade']),
  _HelpArticle(category: 'Support', icon: Icons.wifi_off_rounded, titleEn: 'Why does the app show no internet?', titleHi: 'ऐप में इंटरनेट नहीं है क्यों दिखता है?', summaryEn: 'Live gym data requires secure server connection.', summaryHi: 'लाइव जिम डेटा के लिए सुरक्षित सर्वर कनेक्शन जरूरी है।', answerEn: 'Check mobile data or Wi-Fi, then tap Try Again. GymXBook blocks live pages while offline to avoid showing incorrect zero data. Once connection returns, your verified gym workspace loads again.', answerHi: 'मोबाइल डेटा या Wi-Fi जांचें, फिर Try Again दबाएं। गलत zero data दिखाने से बचने के लिए GymXBook ऑफलाइन होने पर लाइव पेज ब्लॉक करता है। कनेक्शन लौटने पर सत्यापित जिम डेटा फिर लोड होता है।', tipEn: 'If other apps work but GymXBook does not, try switching Wi-Fi/mobile data and login again if session verification fails.', tipHi: 'अगर अन्य ऐप चल रहे हैं लेकिन GymXBook नहीं, तो Wi-Fi/mobile data बदलें और session verification fail होने पर दोबारा login करें।', keywords: ['offline', 'internet', 'connection', 'zero data']),
  _HelpArticle(category: 'Support', icon: Icons.shield_rounded, titleEn: 'How do I keep my account secure?', titleHi: 'अपना खाता सुरक्षित कैसे रखें?', summaryEn: 'Use unique password and secure phone access.', summaryHi: 'अलग पासवर्ड और सुरक्षित फोन एक्सेस रखें।', answerEn: 'Keep your password private, do not share OTP codes, and update your phone number only after verification. Use Google Authenticator for Super Admin access. Logout from shared devices after use.', answerHi: 'पासवर्ड निजी रखें, OTP किसी के साथ शेयर न करें और फोन नंबर केवल verification के बाद बदलें। Super Admin के लिए Google Authenticator का उपयोग करें। साझा डिवाइस से काम के बाद logout करें।', tipEn: 'GymXBook support will never ask for your password, OTP, payment secret or Firebase credential.', tipHi: 'GymXBook support कभी भी आपका password, OTP, payment secret या Firebase credential नहीं मांगेगा।', keywords: ['security', 'password', 'otp', 'login']),
];
