// A trimmed copy of the Flutter app's endpoint registry, for MobileContractDiffTest.
class ApiEndpoints {
  static const String sales = '/sales';
  static String saleVoid(String uuid) => '/sales/$uuid/void';
  static const String customers = '/customers';
  static const String v1Businesses = '/v1/businesses';
  static String v1Switch(String uuid) => '/v1/businesses/${uuid}/switch';
}
